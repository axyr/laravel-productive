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
     * @return array<string, Attribute>  Keyed by name.
     */
    public function attributes(array $properties, array $example = [], array $required = []): array
    {
        $attributes = [];

        foreach ([...array_keys($properties), ...array_keys($example)] as $name) {
            $name = (string) $name;
            $schema = $this->spec->resolve($properties[$name] ?? []);
            $attributes[$name] = new Attribute(
                name: $name,
                property: self::property($name),
                type: self::type($name, $schema, $example[$name] ?? null),
                description: $this->description($name, $schema),
                required: in_array($name, $required, true),
                enum: self::enum($schema),
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
     * The schema type, else the type of the example value, else a date-time for "*_at", else mixed.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function type(string $name, array $schema, mixed $example = null): AttributeType
    {
        return self::schemaType($schema) ?? self::exampleType($example) ?? (str_ends_with($name, '_at') ? AttributeType::DateTime : AttributeType::Mixed);
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
     * @param  array<string, mixed>  $schema
     * @return list<int|string>
     */
    private static function enum(array $schema): array
    {
        $values = is_array($schema['enum'] ?? null) ? $schema['enum'] : [];

        return array_values(array_filter($values, fn(mixed $value): bool => is_int($value) || is_string($value)));
    }
}
