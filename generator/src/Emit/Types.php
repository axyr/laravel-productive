<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;

/**
 * PHP types for IR attributes, in models (read) and inputs (write).
 */
final class Types
{
    /**
     * @return array{type: string, reader: string, doc: string|null}
     */
    public static function model(Attribute $attribute): array
    {
        return match ($attribute->type) {
            AttributeType::String, AttributeType::Time => ['type' => '?string', 'reader' => 'string', 'doc' => null],
            AttributeType::Int => ['type' => '?int', 'reader' => 'int', 'doc' => null],
            AttributeType::Float => ['type' => '?float', 'reader' => 'float', 'doc' => null],
            AttributeType::Bool => ['type' => '?bool', 'reader' => 'bool', 'doc' => null],
            AttributeType::Date => ['type' => '?DateTimeImmutable', 'reader' => 'date', 'doc' => null],
            AttributeType::DateTime => ['type' => '?DateTimeImmutable', 'reader' => 'dateTime', 'doc' => null],
            AttributeType::Object => ['type' => '?array', 'reader' => 'object', 'doc' => 'array<array-key, mixed>|null'],
            AttributeType::List => ['type' => '?array', 'reader' => 'list', 'doc' => 'list<mixed>|null'],
            AttributeType::Mixed => ['type' => 'mixed', 'reader' => 'mixed', 'doc' => null],
        };
    }

    /**
     * @return array{type: string, doc: string|null, format: string|null}
     */
    public static function input(Attribute $attribute): array
    {
        return match ($attribute->type) {
            AttributeType::Int => ['type' => str_ends_with($attribute->name, '_id') ? 'int|string' : 'int', 'doc' => null, 'format' => null],
            AttributeType::Float => ['type' => 'float|int', 'doc' => null, 'format' => null],
            AttributeType::Bool => ['type' => 'bool', 'doc' => null, 'format' => null],
            AttributeType::String => ['type' => 'string', 'doc' => null, 'format' => null],
            AttributeType::Date => ['type' => 'DateTimeInterface|string', 'doc' => null, 'format' => 'date'],
            AttributeType::Time => ['type' => 'DateTimeInterface|string', 'doc' => null, 'format' => 'time'],
            AttributeType::DateTime => ['type' => 'DateTimeInterface|string', 'doc' => null, 'format' => null],
            AttributeType::Object => ['type' => 'array', 'doc' => 'array<string, mixed>', 'format' => null],
            AttributeType::List => ['type' => 'array', 'doc' => 'list<mixed>', 'format' => null],
            AttributeType::Mixed => self::untypedInput($attribute->name),
        };
    }

    /**
     * Untyped request attributes: ID lists and tag lists are known shapes, the rest stays mixed.
     *
     * @return array{type: string, doc: string|null, format: null}
     */
    private static function untypedInput(string $name): array
    {
        return match (true) {
            str_ends_with($name, '_ids') => ['type' => 'array', 'doc' => 'list<int|string>', 'format' => null],
            $name === 'tag_list' => ['type' => 'array', 'doc' => 'list<string>', 'format' => null],
            default => ['type' => 'mixed', 'doc' => null, 'format' => null],
        };
    }
}
