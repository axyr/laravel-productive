<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;

/**
 * Turns spec property schemas into typed attributes.
 */
final readonly class SchemaReader
{
    private const RESERVED_PROPERTIES = ['id', 'type'];

    /**
     * @param  array<string, string>  $descriptions  Replacement descriptions by attribute name, for spec text that describes a filter.
     */
    public function __construct(
        private Spec $spec,
        private array $descriptions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $example  Example values, used to type untyped properties and to add undocumented ones.
     * @param  list<string>  $required
     * @param  bool  $model  Model properties may not shadow Model::$id and Model::$type; input properties may.
     * @return array<string, Attribute>  Keyed by name.
     */
    public function attributes(array $properties, array $example = [], array $required = [], bool $model = true): array
    {
        $attributes = [];

        foreach ([...array_keys($properties), ...array_keys($example)] as $name) {
            $name = (string) $name;
            $schema = $this->spec->resolve($properties[$name] ?? []);
            $attributes[$name] = new Attribute(
                name: $name,
                property: $model ? self::property($name) : Naming::camel($name),
                type: self::type($name, $schema, $example[$name] ?? null),
                description: $this->description($name, $schema),
                required: in_array($name, $required, true),
                enum: self::enum($schema),
                items: $this->items($schema),
            );
        }

        return $attributes;
    }

    /**
     * The camelCase property name. Names that would shadow a Model member ($id, $type) get a "Value" suffix.
     */
    public static function property(string $name): string
    {
        $property = Naming::camel($name);

        return in_array($property, self::RESERVED_PROPERTIES, true) ? $property . 'Value' : $property;
    }

    /**
     * The schema type, else a timestamp for "*_at", else the type of the example value, else a type implied by the name, else mixed.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function type(string $name, array $schema, mixed $example = null): AttributeType
    {
        return self::schemaType($schema) ?? self::timestampType($name) ?? self::exampleType($example) ?? self::nameType($name);
    }

    /**
     * An untyped "*_at" attribute is a timestamp, even when its example is a plain string.
     */
    private static function timestampType(string $name): ?AttributeType
    {
        return str_ends_with($name, '_at') ? AttributeType::DateTime : null;
    }

    /**
     * Untyped and without an example: "currency*" is a currency code.
     */
    private static function nameType(string $name): AttributeType
    {
        return $name === 'currency' || str_starts_with($name, 'currency_') ? AttributeType::String : AttributeType::Mixed;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function schemaType(array $schema): ?AttributeType
    {
        return match ($schema['type'] ?? null) {
            'integer' => AttributeType::Int,
            'number' => AttributeType::Float,
            'boolean' => AttributeType::Bool,
            'object' => AttributeType::Object,
            'array' => AttributeType::List,
            'string' => self::stringType($schema['format'] ?? null),
            default => null,
        };
    }

    private static function stringType(mixed $format): AttributeType
    {
        return match ($format) {
            'date' => AttributeType::Date,
            'date-time' => AttributeType::DateTime,
            'time' => AttributeType::Time,
            default => AttributeType::String,
        };
    }

    private static function exampleType(mixed $example): ?AttributeType
    {
        return match (true) {
            is_bool($example) => AttributeType::Bool,
            is_int($example) => AttributeType::Int,
            is_float($example) => AttributeType::Float,
            is_string($example) => AttributeType::String,
            is_array($example) => array_is_list($example) ? AttributeType::List : AttributeType::Object,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function description(string $name, array $schema): string
    {
        $description = $this->descriptions[$name] ?? (is_string($schema['description'] ?? null) ? $schema['description'] : '');
        $firstLine = trim(explode("\n", trim($description))[0]);

        return str_replace('*/', '* /', $firstLine);
    }

    /**
     * The scalar item type of an array schema (integer weekday IDs), or the scalar value type of
     * an object schema with additionalProperties (a map of IDs to integers).
     *
     * @param  array<string, mixed>  $schema
     */
    private function items(array $schema): ?AttributeType
    {
        $items = match ($schema['type'] ?? null) {
            'array' => self::schemaType($this->spec->resolve($schema['items'] ?? [])),
            'object' => self::schemaType($this->spec->resolve($schema['additionalProperties'] ?? [])),
            default => null,
        };

        return in_array($items, [AttributeType::Int, AttributeType::Float, AttributeType::Bool, AttributeType::String], true) ? $items : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<int|string>
     */
    private static function enum(array $schema): array
    {
        $values = is_array($schema['enum'] ?? null) ? $schema['enum'] : [];

        return array_values(array_filter($values, fn(mixed $value): bool => is_int($value) || is_string($value)));
    }
}
