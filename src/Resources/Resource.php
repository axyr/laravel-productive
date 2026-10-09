<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Data\InputData;
use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Exceptions\BadRequestException;
use Axyr\Productive\Exceptions\InvalidQueryException;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Http\ContentType;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Axyr\Productive\JsonApi\Document;
use Axyr\Productive\JsonApi\DocumentBuilder;
use Axyr\Productive\JsonApi\ResourceObject;
use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\Query\Query;
use Generator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * Shared plumbing for every endpoint group. Concrete resources expose typed public methods
 * and delegate to these helpers, so request building and hydration exist in one place.
 */
abstract class Resource
{
    /** JSON:API resource type sent in request documents, e.g. "tasks". */
    protected const string TYPE = '';

    /** Path relative to the API base URL, e.g. "tasks" or "reports/time_reports". */
    protected const string PATH = '';

    /** Whether list endpoints support cursor pagination (reports do not). */
    protected const bool SUPPORTS_CURSOR = true;

    /** Whether requests carry the X-Organization-Id header (the public endpoints do not). */
    protected const bool REQUIRES_ORGANIZATION = true;

    final public function __construct(
        protected readonly ConnectorInterface $connector,
        protected readonly ModelRegistry $registry = new ModelRegistry(),
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model  Only used to type the returned query.
     * @return PendingQuery<TModel>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    protected function newQuery(string $model, string $operation): PendingQuery
    {
        /** @var PendingQuery<TModel> */
        return new PendingQuery(
            fn(Query $query): ModelCollection => $this->fetchPage($query, $operation),
            fn(Query $query): LazyCollection => $this->lazyPages($query, $operation),
            fn(Query $query, int $perPage, int $page): LengthAwarePaginator => $this->paginatePage($query, $operation, $perPage, $page),
        );
    }

    /**
     * The first page of a list endpoint.
     *
     * @return ModelCollection<Model>
     */
    protected function fetchPage(Query $query, string $operation): ModelCollection
    {
        if ($query->usesCursor() && ! static::SUPPORTS_CURSOR) {
            throw new InvalidQueryException(sprintf('%s does not support cursor pagination; use page() instead.', static::PATH));
        }

        return $this->collect($this->sendRequest(Method::Get, static::PATH, $operation, Expect::Collection, query: $query->toQueryString()));
    }

    /**
     * Every model of a list endpoint, one page at a time. Uses cursor pagination where the
     * endpoint supports it and falls back to page numbers when it does not (reports, or a
     * sort the cursor cannot follow).
     *
     * @return LazyCollection<int, Model>
     */
    protected function lazyPages(Query $query, string $operation): LazyCollection
    {
        if ($query->hasPagePosition()) {
            throw new InvalidQueryException('lazy() and all() read the whole collection from the first page; remove page() and after(), or use get() for a single page.');
        }

        $query = clone $query;

        if ($query->pageSize() === null) {
            $query->perPage(Query::MAX_PAGE_SIZE);
        }

        return LazyCollection::make(function () use ($query, $operation) {
            yield from static::SUPPORTS_CURSOR
                ? $this->cursorPages($query, $operation)
                : $this->numberedPages($query, $operation, 1);
        });
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    protected function paginatePage(Query $query, string $operation, int $perPage, int $page): LengthAwarePaginator
    {
        $models = $this->fetchPage((clone $query)->perPage($perPage)->page($page), $operation);

        return new LengthAwarePaginator($models->all(), $models->totalCount() ?? $models->count(), $perPage, $page);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    protected function fetchOne(string $model, string $path, string $operation, Query $query = new Query()): Model
    {
        return $this->hydrate($model, $this->sendRequest(Method::Get, $path, $operation, Expect::Resource, query: $query->toQueryString()));
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  InputData|array<string, mixed>|null  $attributes
     * @return TModel
     */
    protected function write(string $model, Method $method, string $path, string $operation, InputData|array|null $attributes, ?string $id = null): Model
    {
        return $this->hydrate($model, $this->sendRequest($method, $path, $operation, Expect::Resource, $this->requestDocument($attributes, $id)));
    }

    /**
     * For endpoints that answer with the changed resource or without a body, depending on the case.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  InputData|array<string, mixed>|null  $attributes
     * @return TModel|null
     */
    protected function writeOptional(string $model, Method $method, string $path, string $operation, InputData|array|null $attributes, ?string $id = null): ?Model
    {
        $response = $this->sendRequest($method, $path, $operation, Expect::Resource, $this->requestDocument($attributes, $id));

        return $response->isEmpty() ? null : $this->hydrate($model, $response);
    }

    /**
     * For the few endpoints that take a plain JSON object instead of a JSON:API document.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  InputData|array<string, mixed>  $attributes
     * @return TModel
     */
    protected function writePlain(string $model, Method $method, string $path, string $operation, InputData|array $attributes): Model
    {
        return $this->hydrate($model, $this->sendRequest($method, $path, $operation, Expect::Resource, self::attributes($attributes)));
    }

    /**
     * For actions that only accept a bulk document: one resource object sent with `ext=bulk`.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  InputData|array<string, mixed>  $attributes
     * @return TModel|null
     */
    protected function writeAsBulk(string $model, Method $method, string $path, string $operation, InputData|array $attributes): ?Model
    {
        $body = DocumentBuilder::bulk(static::TYPE, [['attributes' => self::attributes($attributes)]]);
        $response = $this->sendRequest($method, $path, $operation, Expect::Resource, $body, ContentType::JsonApiBulk);

        return $response->isEmpty() ? null : $this->hydrate($model, $response);
    }

    /**
     * For endpoints whose response is not a JSON:API document: a file, a URL or a redirect.
     *
     * @param  InputData|array<string, mixed>|null  $attributes
     */
    protected function raw(Method $method, string $path, string $operation, InputData|array|null $attributes = null, ?string $id = null): Response
    {
        return $this->sendRequest($method, $path, $operation, Expect::Binary, $this->requestDocument($attributes, $id));
    }

    /**
     * @param  InputData|array<string, mixed>|null  $attributes
     */
    protected function writeWithoutResponse(Method $method, string $path, string $operation, InputData|array|null $attributes = null, ?string $id = null): void
    {
        $this->sendRequest($method, $path, $operation, Expect::NoContent, $this->requestDocument($attributes, $id));
    }

    /**
     * @param  list<array{id?: string, attributes?: array<string, mixed>}>  $items
     * @return ModelCollection<Model>
     */
    protected function bulkWrite(Method $method, string $operation, array $items): ModelCollection
    {
        $body = DocumentBuilder::bulk(static::TYPE, $items);

        return $this->collect($this->sendRequest($method, static::PATH, $operation, Expect::Collection, $body, ContentType::JsonApiBulk));
    }

    /**
     * @param  list<int|string>  $ids
     */
    protected function bulkWithoutResponse(Method $method, string $path, string $operation, array $ids): void
    {
        $body = DocumentBuilder::identifiers(static::TYPE, $ids);

        $this->sendRequest($method, $path, $operation, Expect::NoContent, $body, ContentType::JsonApiBulk);
    }

    protected function path(int|string ...$segments): string
    {
        return implode('/', [static::PATH, ...array_map(fn(int|string $segment): string => rawurlencode((string) $segment), $segments)]);
    }

    /**
     * Rate limit buckets on top of the per-token limit every request counts against.
     *
     * @return list<RateLimit>
     */
    protected function rateLimits(): array
    {
        return [];
    }

    /**
     * @param  InputData|array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function attributes(InputData|array $attributes): array
    {
        return $attributes instanceof InputData ? $attributes->toAttributes() : $attributes;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function sendRequest(
        Method $method,
        string $path,
        string $operation,
        Expect $expect,
        ?array $body = null,
        ContentType $contentType = ContentType::JsonApi,
        string $query = '',
    ): Response {
        return $this->connector->send(new Request(
            method: $method,
            path: $path,
            query: $query,
            body: $body,
            contentType: $contentType,
            expect: $expect,
            operation: $operation,
            requiresOrganization: static::REQUIRES_ORGANIZATION,
            rateLimits: $this->rateLimits(),
        ));
    }

    /**
     * @param  InputData|array<string, mixed>|null  $attributes
     * @return array<string, mixed>|null
     */
    private function requestDocument(InputData|array|null $attributes, ?string $id): ?array
    {
        if ($attributes === null) {
            return null;
        }

        return DocumentBuilder::resource(static::TYPE, self::attributes($attributes), $id);
    }

    /**
     * @return Generator<int, Model>
     */
    private function cursorPages(Query $query, string $operation): Generator
    {
        $page = $this->firstCursorPage($query, $operation);

        if ($page === null) {
            yield from $this->numberedPages($query, $operation, 1);

            return;
        }

        while ($page !== null) {
            foreach ($page as $model) {
                yield $model;
            }

            $page = $this->nextCursorPage($page, $operation);
        }
    }

    /**
     * @return ModelCollection<Model>|null Null when the requested sort cannot be paginated by cursor.
     */
    private function firstCursorPage(Query $query, string $operation): ?ModelCollection
    {
        try {
            return $this->fetchPage((clone $query)->after(''), $operation);
        } catch (BadRequestException $exception) {
            return $exception->hasError('keyset_unsupported_sort') ? null : throw $exception;
        }
    }

    /**
     * @param  ModelCollection<Model>  $page
     * @return ModelCollection<Model>|null
     */
    private function nextCursorPage(ModelCollection $page, string $operation): ?ModelCollection
    {
        $next = $page->links()['next'] ?? null;

        if (! is_string($next) || $next === '' || $page->isEmpty()) {
            return null;
        }

        return $this->collect($this->sendRequest(Method::Get, $next, $operation, Expect::Collection));
    }

    /**
     * @return Generator<int, Model>
     */
    private function numberedPages(Query $query, string $operation, int $number): Generator
    {
        do {
            $page = $this->fetchPage((clone $query)->page($number), $operation);

            foreach ($page as $model) {
                yield $model;
            }

            $totalPages = $page->metaInt('total_pages') ?? $number;
            $number++;
        } while ($number <= $totalPages && $page->isNotEmpty());
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function hydrate(string $model, Response $response): Model
    {
        $document = $this->parse($response);
        $hydrated = $this->registry->hydrate($document->resource(), $document->index());

        if (! $hydrated instanceof $model) {
            throw new InvalidResponseException(sprintf('Expected a %s resource, but Productive returned "%s".', $model::TYPE, $hydrated->type));
        }

        return $hydrated;
    }

    /**
     * @return ModelCollection<Model>
     */
    private function collect(Response $response): ModelCollection
    {
        $document = $this->parse($response);
        $index = $document->index();

        return ModelCollection::fromPage(
            array_map(fn(ResourceObject $resource): Model => $this->registry->hydrate($resource, $index), $document->resources()),
            $document->meta,
            $document->links,
        );
    }

    private function parse(Response $response): Document
    {
        $json = $response->json();

        if ($json === []) {
            throw new InvalidResponseException(sprintf('Productive returned an empty body (HTTP %d) where a JSON:API document was expected.', $response->status));
        }

        return Document::fromArray($json);
    }
}
