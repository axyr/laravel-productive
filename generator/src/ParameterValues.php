<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

/**
 * The documented values of the index endpoint's `sort` or `group` parameter.
 */
final class ParameterValues
{
    /**
     * @param  list<array{ClassifiedPath, string, array<string, mixed>}>  $entries
     * @return list<string>
     */
    public static function forIndex(Spec $spec, array $entries, string $name): array
    {
        $index = array_filter($entries, fn(array $entry): bool => $entry[1] === 'GET' && ! $entry[0]->member && $entry[0]->action() === null);
        $first = reset($index);

        return $first === false ? [] : self::values($spec, $first[2], $name);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return list<string>
     */
    private static function values(Spec $spec, array $operation, string $name): array
    {
        $parameters = array_map($spec->resolve(...), is_array($operation['parameters'] ?? null) ? $operation['parameters'] : []);
        $matching = array_filter($parameters, fn(array $parameter): bool => ($parameter['name'] ?? null) === $name);
        $parameter = reset($matching) ?: [];

        return self::enum($spec, $spec->resolve($parameter['schema'] ?? []));
    }

    /**
     * Enum values of a string schema, or of the items of an array schema.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private static function enum(Spec $spec, array $schema): array
    {
        $enum = $schema['enum'] ?? $spec->resolve($schema['items'] ?? [])['enum'] ?? [];

        return Spec::strings($enum, ucfirst(Spec::string($schema['title'] ?? null) ?: 'Parameter') . ' values');
    }
}
