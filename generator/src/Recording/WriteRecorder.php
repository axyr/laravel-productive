<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Http\ContentType;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Axyr\Productive\JsonApi\DocumentBuilder;
use DateTimeImmutable;
use Generator;
use Throwable;

/**
 * Runs the steps of a write plan against the API, or only shows them (a dry run). Attributes may
 * reference "{created:<path>}" (the record this run created of a resource), "{first:<path>}" (the
 * first existing record, read with one GET; "{first:<path>?<query>}" filters the list) and
 * "{date:+N}" (N days from today). A bulk operation is sent as a bulk document of one item: the
 * created record's attributes, its ID with attributes, or its identifier alone. A step whose
 * reference has no record is skipped and says why. Every request and response is recorded,
 * redacted, like the recorder's. What the run created is cleaned up in reverse order after the
 * other steps, also when one of them throws, unless the run keeps it. A 429 stops the run; it is
 * never retried.
 */
final class WriteRecorder
{
    /** @var array<string, string> IDs of the records this run created, by resource path. */
    private array $created = [];

    /** @var array<string, string|null> The first existing record's ID, by resource path. */
    private array $first = [];

    private int $number = 0;

    /**
     * @param  string  $today  The date "{date:+N}" counts from, as Y-m-d.
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly string $today,
    ) {}

    /**
     * @param  list<WriteStep>  $steps
     * @return Generator<string, array<string, mixed>> Recordings by name, e.g. "001.holidays.create".
     */
    public function run(array $steps, bool $execute, bool $keep = false): Generator
    {
        $this->number = 0;
        $failure = null;

        try {
            yield from $this->runSteps(array_filter($steps, fn(WriteStep $step): bool => ! $step->cleanup), $execute);
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        if (! $keep) {
            yield from $this->runSteps($this->cleanups($steps, $execute), $execute);
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * @param  array<WriteStep>  $steps
     * @return Generator<string, array<string, mixed>>
     */
    private function runSteps(array $steps, bool $execute): Generator
    {
        foreach ($steps as $step) {
            yield sprintf('%03d.%s', ++$this->number, $step->operation->key) => $this->step($step, $execute);
        }
    }

    /**
     * The cleanups of what the run created; a dry run shows every cleanup.
     *
     * @param  list<WriteStep>  $steps
     * @return array<WriteStep>
     */
    private function cleanups(array $steps, bool $execute): array
    {
        return array_filter($steps, fn(WriteStep $step): bool => $step->cleanup && (! $execute || isset($this->created[$step->resource])));
    }

    /**
     * @return array<string, mixed>
     */
    private function step(WriteStep $step, bool $execute): array
    {
        try {
            [$id, $attributes] = $this->prepare($step, $execute);
        } catch (MissingReference $missing) {
            return ['skipped' => $missing->getMessage()];
        }

        $method = Method::from($step->operation->httpMethod);
        $path = str_replace('{id}', (string) $id, $step->operation->path);
        $body = self::body($step, $method, $id, $attributes);
        $recording = ['request' => ['method' => $method->value, 'path' => $path, 'body' => $body]];

        return $execute ? [...$recording, ...$this->send($step, new Request($method, $path, body: $body, contentType: $step->operation->bulk ? ContentType::JsonApiBulk : ContentType::JsonApi, operation: $step->operation->key, requiresOrganization: $step->operation->requiresOrganization, rateLimits: [Recorder::pace()]))] : $recording;
    }

    /**
     * The JSON:API document of the request; an update carries the record's ID, a create none.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>|null
     */
    private static function body(WriteStep $step, Method $method, ?string $id, ?array $attributes): ?array
    {
        $id = $method === Method::Post ? null : $id;

        if ($step->operation->bulk) {
            return self::bulkBody($step->type, $id, $attributes);
        }

        return $attributes === null ? null : DocumentBuilder::resource($step->type, $attributes, $id);
    }

    /**
     * A bulk document of one item: attributes for a create, the ID and attributes for an update,
     * the identifier alone for an action or a delete.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>
     */
    private static function bulkBody(string $type, ?string $id, ?array $attributes): array
    {
        if ($attributes === null) {
            return DocumentBuilder::identifiers($type, $id === null ? [] : [$id]);
        }

        return DocumentBuilder::bulk($type, [$id === null ? ['attributes' => $attributes] : ['id' => $id, 'attributes' => $attributes]]);
    }

    /**
     * The created record's ID the step targets, and its attributes with every reference resolved.
     *
     * @return array{string|null, array<string, mixed>|null}
     *
     * @throws MissingReference
     */
    private function prepare(WriteStep $step, bool $execute): array
    {
        return [
            $step->onCreated ? $this->createdId($step->resource, $execute) : null,
            $step->attributes === null ? null : $this->resolve($step->attributes, $execute),
        ];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: mixed}
     */
    private function send(WriteStep $step, Request $request): array
    {
        try {
            $response = $this->connector->send($request);
        } catch (RateLimitException $exception) {
            throw $exception;
        } catch (ApiException $exception) {
            $response = $exception->response;
        }

        $this->rememberCreated($step, $response);

        return Exchange::response($response);
    }

    private function rememberCreated(WriteStep $step, Response $response): void
    {
        if (! $step->creates || ! $response->successful()) {
            return;
        }

        $document = Json::decode($response->body);
        $id = self::idOf(data_get($document, 'data.id') ?? data_get($document, 'data.0.id'));

        if ($id !== null) {
            $this->created[$step->resource] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws MissingReference
     */
    private function resolve(array $attributes, bool $execute): array
    {
        $resolved = [];

        foreach ($attributes as $name => $value) {
            $resolved[$name] = $this->resolveValue($value, $execute);
        }

        return $resolved;
    }

    /**
     * @throws MissingReference
     */
    private function resolveValue(mixed $value, bool $execute): mixed
    {
        return is_array($value) ? array_map(fn(mixed $item): mixed => $this->resolveValue($item, $execute), $value) : $this->value($value, $execute);
    }

    /**
     * The ID of the record this run created of a resource; a placeholder in a dry run.
     *
     * @throws MissingReference
     */
    private function createdId(string $resource, bool $execute): string
    {
        return $execute ? $this->created[$resource] ?? throw new MissingReference(sprintf('This run created no %s.', $resource)) : '{created:' . $resource . '}';
    }

    /**
     * @throws MissingReference
     */
    private function value(mixed $value, bool $execute): mixed
    {
        if (! is_string($value) || preg_match('/^\{(created|first|date):([^}]+)\}$/', $value, $reference) !== 1) {
            return $value;
        }

        return match ($reference[1]) {
            'created' => $this->createdId($reference[2], $execute),
            'first' => $this->firstId($reference[2]),
            default => (new DateTimeImmutable($this->today))->modify($reference[2] . ' days')->format('Y-m-d'),
        };
    }

    /**
     * @throws MissingReference
     */
    private function firstId(string $path): string
    {
        if (! array_key_exists($path, $this->first)) {
            $this->first[$path] = $this->readFirstId($path);
        }

        return $this->first[$path] ?? throw new MissingReference(sprintf('There are no %s.', $path));
    }

    /**
     * @param  string  $list  The resource path, optionally with a query: "people?filter[role_id]=1".
     *
     * @throws MissingReference When the list cannot be read.
     */
    private function readFirstId(string $list): ?string
    {
        [$path, $query] = [...explode('?', $list, 2), ''];

        try {
            $response = $this->connector->send(new Request(Method::Get, $path, ltrim($query . '&page[size]=1', '&'), operation: $path . '.index', rateLimits: [Recorder::pace()]));
        } catch (RateLimitException $exception) {
            throw $exception;
        } catch (ApiException $exception) {
            throw new MissingReference(sprintf('Could not list %s: %s', $path, $exception->getMessage()));
        }

        return self::idOf(data_get(Json::decode($response->body), 'data.0.id'));
    }

    private static function idOf(mixed $id): ?string
    {
        return is_string($id) || is_int($id) ? (string) $id : null;
    }
}
