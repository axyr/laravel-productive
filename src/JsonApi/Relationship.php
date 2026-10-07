<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

use Axyr\Productive\Exceptions\InvalidResponseException;

/**
 * A relationship as returned by Productive.
 *
 * Productive omits `data` for relationships that were not requested with `include`
 * and returns `"meta": {"included": false}` instead, so "no data" is not the same as "empty".
 */
final readonly class Relationship
{
    /**
     * @param  ResourceIdentifier|list<ResourceIdentifier>|null  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public function __construct(
        public ResourceIdentifier|array|null $data,
        public bool $hasData,
        public array $meta = [],
        public array $links = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $relationship
     */
    public static function fromArray(array $relationship): self
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($relationship['meta'] ?? null) ? $relationship['meta'] : [];
        /** @var array<string, mixed> $links */
        $links = is_array($relationship['links'] ?? null) ? $relationship['links'] : [];

        if (! array_key_exists('data', $relationship)) {
            return new self(null, false, $meta, $links);
        }

        return new self(self::parseData($relationship['data']), true, $meta, $links);
    }

    public function isToMany(): bool
    {
        return is_array($this->data);
    }

    /**
     * @return list<ResourceIdentifier>
     */
    public function identifiers(): array
    {
        if ($this->data === null) {
            return [];
        }

        return is_array($this->data) ? $this->data : [$this->data];
    }

    /**
     * @return ResourceIdentifier|list<ResourceIdentifier>|null
     */
    private static function parseData(mixed $data): ResourceIdentifier|array|null
    {
        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw new InvalidResponseException('JSON:API relationship data must be null, an object or an array.');
        }

        return array_is_list($data) ? array_map(self::identifier(...), $data) : ResourceIdentifier::fromArray($data);
    }

    private static function identifier(mixed $item): ResourceIdentifier
    {
        if (! is_array($item)) {
            throw new InvalidResponseException('JSON:API to-many relationship data must contain objects.');
        }

        return ResourceIdentifier::fromArray($item);
    }
}
