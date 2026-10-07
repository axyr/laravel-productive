<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

use Axyr\Productive\Exceptions\InvalidResponseException;
use JsonSerializable;

final readonly class ResourceIdentifier implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(self::parseType($data['type'] ?? null), self::parseId($data['id'] ?? null));
    }

    private static function parseType(mixed $type): string
    {
        if (! is_string($type) || $type === '') {
            throw new InvalidResponseException('A JSON:API resource identifier needs a non-empty string "type".');
        }

        return $type;
    }

    private static function parseId(mixed $id): string
    {
        if (is_int($id) || (is_string($id) && $id !== '')) {
            return (string) $id;
        }

        throw new InvalidResponseException('A JSON:API resource identifier needs a non-empty "id".');
    }

    public function key(): string
    {
        return $this->type . ':' . $this->id;
    }

    /**
     * @return array{type: string, id: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }

    /**
     * @return array{type: string, id: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
