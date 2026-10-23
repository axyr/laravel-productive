<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Http\Request;
use Generator;

/**
 * Records real API responses with GET requests only: one page of every index, the first record of
 * every show, the index again with its relationships included, the GET actions that only read,
 * the show of resources without an index (by an ID seen in another response, or a configured
 * uuid for public links), and a status probe of each undocumented resource. A 429 stops the run,
 * so a rate-limited response is never recorded.
 */
final class Recorder
{
    public const string PAGE_SIZE = '20';

    public const string INCLUDE_PAGE_SIZE = '5';

    /** Resources that are never recorded: authentication. */
    private const array SKIPPED = ['sessions', 'passwords'];

    private readonly SeenIds $ids;

    /**
     * @param  list<string>  $undocumented  Paths outside the spec, probed for their status only.
     * @param  array<string, string>  $filters  Extra query per index path, e.g. a date range for reports, which are expensive without one.
     * @param  array<string, list<string>>  $untyped  Relationships an earlier recording saw without data, by type: included too, to learn their cardinality.
     * @param  list<string>  $actions  Keys of GET actions that only read, called on the first record.
     * @param  array<string, string>  $publicIds  The uuid to fetch a `public/*` resource with, by path.
     */
    public function __construct(
        private readonly Api $api,
        private readonly ConnectorInterface $connector,
        private readonly array $undocumented = [],
        private readonly array $filters = [],
        private readonly array $untyped = [],
        private readonly array $actions = [],
        private readonly array $publicIds = [],
    ) {
        $this->ids = new SeenIds();
    }

    /**
     * Applied to every request on top of Productive's own limits, so a run is paced instead of bursting.
     */
    public static function pace(): RateLimit
    {
        return new RateLimit('recorder', 1, 1);
    }

    /**
     * Reports allow 10 requests per 30 seconds. Counting in fixed windows can still put 20 within
     * a sliding 30 seconds, and the recorder never retries a 429, so reports go one per 4 seconds:
     * any 30 seconds touch at most 9 of those windows (with 3-second windows, 11).
     */
    public static function reportPace(): RateLimit
    {
        return new RateLimit('recorder-reports', 1, 4);
    }

    /**
     * @return Generator<string, array<string, mixed>> Recordings by name, e.g. "tasks.index".
     */
    public function record(): Generator
    {
        foreach (array_filter($this->api->resources, self::isRecorded(...)) as $resource) {
            yield from $this->recordResource($resource);
        }

        // Last, so every ID another response carried is known.
        foreach ($this->api->resources as $resource) {
            yield from $this->recordShowOnly($resource);
        }

        foreach ($this->undocumented as $path) {
            yield 'undocumented.' . str_replace('/', '.', $path) => $this->probe($path);
        }
    }

    /**
     * The show of a resource without an index, when an ID is known: the first seen of its type,
     * or the configured uuid of a public link.
     *
     * @return Generator<string, array<string, mixed>>
     */
    private function recordShowOnly(Resource $resource): Generator
    {
        $show = self::operation($resource, OperationKind::Show);

        if ($show === null || self::operation($resource, OperationKind::Index) !== null) {
            return;
        }

        $id = $this->showOnlyId($resource);

        if ($id !== null) {
            yield $show->key => $this->getRecord($show, $id, []);
        }
    }

    private static function isRecorded(Resource $resource): bool
    {
        return ! in_array($resource->path, self::SKIPPED, true) && ! str_starts_with($resource->path, 'public/');
    }

    private function showOnlyId(Resource $resource): ?string
    {
        if (in_array($resource->path, self::SKIPPED, true)) {
            return null;
        }

        return str_starts_with($resource->path, 'public/') ? $this->publicIds[$resource->path] ?? null : $this->ids->first($resource->type);
    }

    /**
     * @return array{request: array{method: string, path: string, query: string}, status: int}
     */
    private function probe(string $path): array
    {
        $recording = $this->get($path, 'page[size]=1', 'undocumented', true, []);

        return ['request' => $recording['request'], 'status' => $recording['status']];
    }

    /**
     * @return Generator<string, array<string, mixed>>
     */
    private function recordResource(Resource $resource): Generator
    {
        $index = self::operation($resource, OperationKind::Index);

        if ($index === null) {
            return;
        }

        $limits = $resource->reportRateLimit ? [RateLimit::reports(), self::reportPace()] : [];
        $recording = $this->get($index->path, $this->indexQuery($index, self::PAGE_SIZE), $index->key, $index->requiresOrganization, $limits);

        yield $index->key => $recording;

        $id = self::firstId($recording['body']);

        if ($id !== null) {
            yield from $this->recordRecord($resource, $index, $id, $limits);
        }
    }

    /**
     * Records the first record of an index, and the index with its relationships included.
     *
     * @param  list<RateLimit>  $limits
     * @return Generator<string, array<string, mixed>>
     */
    private function recordRecord(Resource $resource, Operation $index, string $id, array $limits): Generator
    {
        $show = self::operation($resource, OperationKind::Show);

        if ($show !== null) {
            yield $show->key => $this->getRecord($show, $id, $limits);
        }

        yield from $this->recordActions($resource, $id, $limits);

        $known = array_map(fn(Relationship $relationship): string => $relationship->name, $this->api->model((string) $resource->model)->relationships ?? []);
        $relationships = array_unique([...$known, ...$this->untyped[$resource->type] ?? []]);

        yield from $this->recordIncludes($index, $relationships, $limits);
    }

    /**
     * Records the GET actions that only read, on the given record.
     *
     * @param  list<RateLimit>  $limits
     * @return Generator<string, array<string, mixed>>
     */
    private function recordActions(Resource $resource, string $id, array $limits): Generator
    {
        foreach ($resource->operations as $operation) {
            if ($operation->httpMethod === 'GET' && in_array($operation->key, $this->actions, true)) {
                yield $operation->key => $this->getRecord($operation, $id, $limits);
            }
        }
    }

    /**
     * @param  list<RateLimit>  $limits
     * @return array{request: array{method: string, path: string, query: string}, status: int, headers: array<string, string>, body: mixed}
     */
    private function getRecord(Operation $operation, string $id, array $limits): array
    {
        $path = str_replace(['{id}', '{uuid}'], rawurlencode($id), $operation->path);

        return $this->get($path, '', $operation->key, $operation->requiresOrganization, $limits);
    }

    /**
     * Records the index with the given relationships included. A 400 means one of them cannot be
     * included: the list is split in halves until each failing relationship is recorded on its own.
     *
     * @param  array<string>  $relationships
     * @param  list<RateLimit>  $limits
     * @return Generator<string, array<string, mixed>>
     */
    private function recordIncludes(Operation $index, array $relationships, array $limits, bool $complete = true): Generator
    {
        if ($relationships === []) {
            return;
        }

        $query = $this->indexQuery($index, self::INCLUDE_PAGE_SIZE) . '&include=' . implode(',', $relationships);
        $recording = $this->get($index->path, $query, $index->key, $index->requiresOrganization, $limits);

        if ($recording['status'] !== 400 || count($relationships) === 1) {
            yield self::includeName($index, $relationships, $complete) => $recording;

            return;
        }

        $half = intdiv(count($relationships), 2);

        yield from $this->recordIncludes($index, array_slice($relationships, 0, $half), $limits, false);
        yield from $this->recordIncludes($index, array_slice($relationships, $half), $limits, false);
    }

    /**
     * "tasks.index.include" for the full list, "tasks.index.include.project+assignee" for part of it.
     *
     * @param  array<string>  $relationships
     */
    private static function includeName(Operation $index, array $relationships, bool $complete): string
    {
        return $index->key . '.include' . ($complete ? '' : '.' . implode('+', $relationships));
    }

    private function indexQuery(Operation $index, string $size): string
    {
        $filter = $this->filters[$index->path] ?? '';

        return ($filter === '' ? '' : $filter . '&') . 'page[size]=' . $size;
    }

    /**
     * @param  list<RateLimit>  $limits
     * @return array{request: array{method: string, path: string, query: string}, status: int, headers: array<string, string>, body: mixed}
     */
    private function get(string $path, string $query, string $operation, bool $requiresOrganization, array $limits): array
    {
        $request = new Request(Method::Get, $path, $query, operation: $operation, requiresOrganization: $requiresOrganization, rateLimits: [...$limits, self::pace()]);

        try {
            $response = $this->connector->send($request);
        } catch (RateLimitException $exception) {
            throw $exception;
        } catch (ApiException $exception) {
            $response = $exception->response;
        }

        $body = Json::decode($response->body);
        $this->ids->remember($response->successful() ? $body : null);

        return [
            'request' => ['method' => 'GET', 'path' => $path, 'query' => $query],
            'status' => $response->status,
            'headers' => array_filter([
                'Content-Type' => $response->header('Content-Type'),
                'X-RateLimit-Limit' => $response->header('X-RateLimit-Limit'),
                'X-RateLimit-Remaining' => $response->header('X-RateLimit-Remaining'),
                'X-RateLimit-Reset' => $response->header('X-RateLimit-Reset'),
            ], fn(?string $value): bool => $value !== null),
            'body' => Redactor::redact($body),
        ];
    }


    private static function operation(Resource $resource, OperationKind $kind): ?Operation
    {
        foreach ($resource->operations as $operation) {
            if ($operation->kind === $kind && $operation->httpMethod === 'GET') {
                return $operation;
            }
        }

        return null;
    }

    private static function firstId(mixed $body): ?string
    {
        $id = data_get($body, 'data.0.id');

        return is_string($id) || is_int($id) ? (string) $id : null;
    }
}
