<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

/**
 * Reads values from JSON:API `meta` objects.
 */
final readonly class Meta
{
    /**
     * A non-negative integer such as total_count or total_pages, sent as a number or a numeric string.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function int(array $meta, string $key): ?int
    {
        $value = $meta[$key] ?? null;

        return match (true) {
            is_int($value) => $value,
            is_string($value) && ctype_digit($value) => (int) $value,
            default => null,
        };
    }
}
