<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Closure;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

/**
 * Returned by Productive::fake(): seed responses and assert on the requests that were made.
 */
final readonly class ProductiveFake
{
    public function __construct(
        private FakeConnector $connector,
    ) {}

    public function connector(): FakeConnector
    {
        return $this->connector;
    }

    /**
     * @param  Response|ResponseSequence|Closure(Request): Response  $response
     */
    public function respond(string $pattern, Response|ResponseSequence|Closure $response): self
    {
        $this->connector->respond($pattern, $response);

        return $this;
    }

    public function preventStrayRequests(bool $prevent = true): self
    {
        $this->connector->preventStrayRequests($prevent);

        return $this;
    }

    /**
     * @param  (Closure(Request): bool)|null  $filter
     * @return list<Request>
     */
    public function recorded(?Closure $filter = null): array
    {
        $requests = $this->connector->recorded();

        return $filter === null ? $requests : array_values(array_filter($requests, $filter));
    }

    /**
     * @param  string|(Closure(Request): bool)  $operation  An operation pattern such as "tasks.create" or "tasks.*", or a callback.
     */
    public function assertSent(string|Closure $operation): void
    {
        Assert::assertNotEmpty(
            $this->recorded($this->filter($operation)),
            is_string($operation) ? sprintf('No request was sent for operation [%s].', $operation) : 'No matching request was sent.',
        );
    }

    /**
     * @param  string|(Closure(Request): bool)  $operation
     */
    public function assertNotSent(string|Closure $operation): void
    {
        Assert::assertEmpty(
            $this->recorded($this->filter($operation)),
            is_string($operation) ? sprintf('An unexpected request was sent for operation [%s].', $operation) : 'An unexpected request was sent.',
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->connector->recorded(), sprintf('Expected %d requests, %d were sent.', $count, count($this->connector->recorded())));
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /**
     * Assert a resource of the given JSON:API type was created, optionally with matching attributes.
     *
     * @param  (Closure(array<string, mixed>): bool)|null  $attributes
     */
    public function assertCreated(string $type, ?Closure $attributes = null): void
    {
        $this->assertWritten(Method::Post, $type, null, $attributes, sprintf('No %s resource was created', $type));
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $attributes
     */
    public function assertUpdated(string $type, int|string $id, ?Closure $attributes = null): void
    {
        $this->assertWritten(Method::Patch, $type, (string) $id, $attributes, sprintf('%s %s was not updated', $type, $id));
    }

    /**
     * @param  string  $path  The resource path, e.g. "tasks".
     */
    public function assertDeleted(string $path, int|string $id): void
    {
        $expected = $path . '/' . $id;

        $this->assertSent(fn(Request $request): bool => $request->method === Method::Delete && $request->path === $expected);
    }

    /**
     * @param  string|(Closure(Request): bool)  $operation
     * @return Closure(Request): bool
     */
    private function filter(string|Closure $operation): Closure
    {
        return is_string($operation) ? fn(Request $request): bool => Str::is($operation, $request->operation) : $operation;
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $attributes
     */
    private function assertWritten(Method $method, string $type, ?string $id, ?Closure $attributes, string $message): void
    {
        $matching = $this->recorded($this->writeMatcher($method, $type, $id, $attributes));

        Assert::assertNotEmpty($matching, $message . ($attributes === null ? '.' : ' with matching attributes.'));
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $attributes
     * @return Closure(Request): bool
     */
    private function writeMatcher(Method $method, string $type, ?string $id, ?Closure $attributes): Closure
    {
        return function (Request $request) use ($method, $type, $id, $attributes): bool {
            $data = $this->resourceData($request, $method);

            return $data !== null && self::isResource($data, $type, $id) && ($attributes === null || $attributes(self::attributesOf($data)));
        };
    }

    /**
     * The single resource object a request sent with the given method, if any.
     *
     * @return array<array-key, mixed>|null
     */
    private function resourceData(Request $request, Method $method): ?array
    {
        $data = $request->body['data'] ?? null;

        return $request->method === $method && is_array($data) ? $data : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function isResource(array $data, string $type, ?string $id): bool
    {
        return ($data['type'] ?? null) === $type && ($id === null || ($data['id'] ?? null) === $id);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributesOf(array $data): array
    {
        /** @var array<string, mixed> */
        return is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
    }
}
