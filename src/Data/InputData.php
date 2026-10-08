<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

use BackedEnum;
use DateTimeInterface;

/**
 * Base class for request attribute objects. Fields left at Undefined::Value are not sent;
 * an explicit null is sent as null.
 */
abstract readonly class InputData
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toAttributes(): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function filter(array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if ($value !== Undefined::Value) {
                $filtered[$key] = self::serialize($value);
            }
        }

        return $filtered;
    }

    /**
     * Formats a calendar date field ("Y-m-d"); strings, null and Undefined pass through.
     */
    protected static function date(DateTimeInterface|string|Undefined|null $value): string|Undefined|null
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    /**
     * Formats a time-of-day field ("H:i"); strings, null and Undefined pass through.
     */
    protected static function time(DateTimeInterface|string|Undefined|null $value): string|Undefined|null
    {
        return $value instanceof DateTimeInterface ? $value->format('H:i') : $value;
    }

    private static function serialize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            is_array($value) => array_map(self::serialize(...), $value),
            default => $value,
        };
    }
}
