<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

use Axyr\Productive\Exceptions\InvalidResponseException;

final readonly class ResourceObject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, Relationship>  $relationships
     * @param  array<string, mixed>  $links
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $attributes = [],
        public array $relationships = [],
        public array $links = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $identifier = ResourceIdentifier::fromArray($data);

        return new self(
            type: $identifier->type,
            id: $identifier->id,
            attributes: self::object($data, 'attributes'),
            relationships: array_map(
                fn(mixed $relationship): Relationship => is_array($relationship)
                    ? Relationship::fromArray($relationship)
                    : throw new InvalidResponseException('A JSON:API relationship must be an object.'),
                self::object($data, 'relationships'),
            ),
            links: self::object($data, 'links'),
            meta: self::object($data, 'meta'),
        );
    }

    public function identifier(): ResourceIdentifier
    {
        return new ResourceIdentifier($this->type, $this->id);
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function relationship(string $name): ?Relationship
    {
        return $this->relationships[$name] ?? null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private static function object(array $data, string $member): array
    {
        $value = $data[$member] ?? [];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidResponseException(sprintf('JSON:API member "%s" must be an object.', $member));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
