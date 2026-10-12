<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConfigurableConnector;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\StrayRequestException;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Closure;
use Illuminate\Support\Str;

/**
 * Records requests and answers them from seeded responses, without any HTTP.
 *
 * Unseeded requests get a plausible default (an empty collection, the submitted resource
 * echoed back with an ID, or 204) unless stray requests are prevented.
 */
final class FakeConnector implements ConfigurableConnector
{
    private readonly FakeConnectorState $state;

    /**
     * @param  array<string, Response|ResponseSequence|Closure(Request): Response>  $responses
     */
    public function __construct(
        array $responses = [],
        ?FakeConnectorState $state = null,
        private readonly string $organizationId = '',
    ) {
        $this->state = $state ?? new FakeConnectorState($responses);
    }

    /**
     * The same fake, recording requests as sent for the configuration's organization.
     */
    public function forConfig(ProductiveConfig $config): self
    {
        return new self(state: $this->state, organizationId: $config->organizationId);
    }

    /**
     * Seed a response that takes precedence over every earlier seed. Among the seeds given to
     * fake(), the first match wins.
     *
     * @param  Response|ResponseSequence|Closure(Request): Response  $response
     */
    public function respond(string $pattern, Response|ResponseSequence|Closure $response): void
    {
        unset($this->state->responses[$pattern]);
        $this->state->responses = [$pattern => $response, ...$this->state->responses];
    }

    public function preventStrayRequests(bool $prevent = true): void
    {
        $this->state->preventStrayRequests = $prevent;
    }

    public function send(Request $request): Response
    {
        $request = self::onTheWire($request);
        $this->state->recorded[] = [$request, $this->organizationId];
        $response = $this->responseFor($request);

        if (! $response->successful()) {
            throw ApiException::fromResponse($request, $response);
        }

        return $response;
    }

    /**
     * @return list<Request>
     */
    public function recorded(): array
    {
        return array_column($this->state->recorded, 0);
    }

    /**
     * @return list<string>  The organization each recorded request was sent for, in the same order.
     */
    public function organizations(): array
    {
        return array_column($this->state->recorded, 1);
    }

    /**
     * The request as the real connector sends it: the body goes through JSON, so a value that
     * cannot be encoded fails here too, and dates or objects arrive as they would on the wire.
     */
    private static function onTheWire(Request $request): Request
    {
        /** @var array<string, mixed>|null $body */
        $body = json_decode(json_encode($request->body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES), true, flags: JSON_THROW_ON_ERROR);

        return new Request($request->method, $request->path, $request->query, $body, $request->contentType, $request->expect, $request->operation, $request->requiresOrganization, $request->rateLimits);
    }

    private function responseFor(Request $request): Response
    {
        foreach ($this->state->responses as $pattern => $response) {
            if ($this->matches($pattern, $request)) {
                return match (true) {
                    $response instanceof ResponseSequence => $response->next($request),
                    $response instanceof Closure => $response($request),
                    default => $response,
                };
            }
        }

        if ($this->state->preventStrayRequests) {
            throw new StrayRequestException(sprintf('No fake response seeded for [%s] (operation "%s").', $request->describe(), $request->operation));
        }

        return $this->defaultResponse($request);
    }

    private function matches(string $pattern, Request $request): bool
    {
        if (! str_contains($pattern, ' ')) {
            return Str::is($pattern, $request->operation);
        }

        [$method, $path] = explode(' ', $pattern, 2);

        return strtoupper($method) === $request->method->value && Str::is($path, self::relativePath($request->path));
    }

    /**
     * "tasks?page[after]=x" for a next-page link "https://api.productive.io/api/v2/tasks?page[after]=x",
     * so "GET tasks" patterns match every page; relative paths are returned as is.
     */
    private static function relativePath(string $path): string
    {
        return preg_match('#^https?://[^/]+/api/v\d+/(.*)$#s', $path, $match) === 1 ? $match[1] : $path;
    }

    private function defaultResponse(Request $request): Response
    {
        return match ($request->expect) {
            Expect::Collection => FakeResponse::collection(),
            Expect::NoContent => FakeResponse::noContent(),
            Expect::Binary => FakeResponse::binary(''),
            Expect::Resource => $this->echo($request),
        };
    }

    /**
     * Echo the submitted resource back, as Productive does, with an ID taken from the path or generated.
     * The type comes from the path, after "public/" for the public endpoints.
     */
    private function echo(Request $request): Response
    {
        $data = is_array($request->body['data'] ?? null) ? $request->body['data'] : [];
        $segments = self::segments($request->path);

        return FakeResponse::resource([
            'type' => $data['type'] ?? $segments[0],
            'id' => $data['id'] ?? $this->idFromPath($segments),
            'attributes' => $data['attributes'] ?? [],
        ], status: $request->method === Method::Post ? 201 : 200);
    }

    /**
     * The path segments from the resource on: "public/pages/abc" gives ["pages", "abc"].
     *
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $segments = explode('/', explode('?', self::relativePath($path))[0]);

        return $segments[0] === 'public' ? array_slice($segments, 1) : $segments;
    }

    /**
     * @param  list<string>  $segments
     */
    private function idFromPath(array $segments): string
    {
        $id = $segments[1] ?? null;

        return $id !== null && ctype_digit($id) ? $id : (string) $this->state->nextId++;
    }
}
