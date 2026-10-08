<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Contracts\ConnectorInterface;
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
final class FakeConnector implements ConnectorInterface
{
    /** @var list<Request> */
    private array $recorded = [];

    private bool $preventStrayRequests = false;

    private int $nextId = 1;

    /**
     * @param  array<string, Response|ResponseSequence|Closure(Request): Response>  $responses
     */
    public function __construct(
        private array $responses = [],
    ) {}

    /**
     * @param  Response|ResponseSequence|Closure(Request): Response  $response
     */
    public function respond(string $pattern, Response|ResponseSequence|Closure $response): void
    {
        $this->responses[$pattern] = $response;
    }

    public function preventStrayRequests(bool $prevent = true): void
    {
        $this->preventStrayRequests = $prevent;
    }

    public function send(Request $request): Response
    {
        $this->recorded[] = $request;
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
        return $this->recorded;
    }

    private function responseFor(Request $request): Response
    {
        foreach ($this->responses as $pattern => $response) {
            if ($this->matches($pattern, $request)) {
                return match (true) {
                    $response instanceof ResponseSequence => $response->next($request),
                    $response instanceof Closure => $response($request),
                    default => $response,
                };
            }
        }

        if ($this->preventStrayRequests) {
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

        return strtoupper($method) === $request->method->value && Str::is($path, $request->path);
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
     */
    private function echo(Request $request): Response
    {
        $data = is_array($request->body['data'] ?? null) ? $request->body['data'] : [];
        $segments = explode('/', $request->path);

        return FakeResponse::resource([
            'type' => $data['type'] ?? $segments[0],
            'id' => $data['id'] ?? $this->idFromPath($segments),
            'attributes' => $data['attributes'] ?? [],
        ], status: $request->method === Method::Post ? 201 : 200);
    }

    /**
     * @param  list<string>  $segments
     */
    private function idFromPath(array $segments): string
    {
        $id = $segments[1] ?? null;

        return $id !== null && ctype_digit($id) ? $id : (string) $this->nextId++;
    }
}
