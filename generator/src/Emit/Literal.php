<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

/**
 * Exports values as PHP literals in the project's style: short arrays, single quotes.
 */
final class Literal
{
    public static function export(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::float($value),
            is_string($value) => self::string($value),
            is_array($value) => self::array($value),
            default => throw new \InvalidArgumentException(sprintf('Cannot export a %s as a PHP literal.', get_debug_type($value))),
        };
    }

    public static function string(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private static function float(float $value): string
    {
        return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function array(array $value): string
    {
        $items = array_is_list($value)
            ? array_map(self::export(...), $value)
            : array_map(fn(int|string $key, mixed $item): string => self::export($key) . ' => ' . self::export($item), array_keys($value), $value);

        return '[' . implode(', ', $items) . ']';
    }
}
