<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

use Axyr\Productive\Exceptions\InvalidResponseException;

final readonly class Document
{
    /**
     * @param  ResourceObject|list<ResourceObject>|null  $data
     * @param  list<ResourceObject>  $included
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public function __construct(
        public ResourceObject|array|null $data,
        public array $included = [],
        public array $meta = [],
        public array $links = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $document
     */
    public static function fromArray(array $document): self
    {
        if (! array_key_exists('data', $document)) {
            throw new InvalidResponseException('The response is not a JSON:API document: the "data" member is missing.');
        }

        return new self(
            data: self::parseData($document['data']),
            included: self::parseList($document['included'] ?? []),
            meta: self::object($document['meta'] ?? []),
            links: self::object($document['links'] ?? []),
        );
    }

    public function isCollection(): bool
    {
        return is_array($this->data);
    }

    public function resource(): ResourceObject
    {
        if (! $this->data instanceof ResourceObject) {
            throw new InvalidResponseException('Expected a single resource, but the response contains a collection or no data.');
        }

        return $this->data;
    }

    /**
     * @return list<ResourceObject>
     */
    public function resources(): array
    {
        if (! is_array($this->data)) {
            throw new InvalidResponseException('Expected a collection, but the response contains a single resource or no data.');
        }

        return $this->data;
    }

    public function index(): ResourceIndex
    {
        $primary = match (true) {
            is_array($this->data) => $this->data,
            $this->data instanceof ResourceObject => [$this->data],
            default => [],
        };

        return new ResourceIndex([...$primary, ...$this->included]);
    }

    public function link(string $name): ?string
    {
        $link = $this->links[$name] ?? null;

        if (is_array($link)) {
            $link = $link['href'] ?? null;
        }

        return is_string($link) && $link !== '' ? $link : null;
    }

    public function metaInt(string $key): ?int
    {
        $value = $this->meta[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return ResourceObject|list<ResourceObject>|null
     */
    private static function parseData(mixed $data): ResourceObject|array|null
    {
        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw new InvalidResponseException('JSON:API "data" must be null, an object or an array.');
        }

        return array_is_list($data) ? self::parseList($data) : ResourceObject::fromArray($data);
    }

    /**
     * @return list<ResourceObject>
     */
    private static function parseList(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidResponseException('A JSON:API resource list must be an array.');
        }

        return array_map(
            fn(mixed $item): ResourceObject => is_array($item)
                ? ResourceObject::fromArray($item)
                : throw new InvalidResponseException('A JSON:API resource list must contain objects.'),
            $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidResponseException('JSON:API "meta" and "links" must be objects.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
