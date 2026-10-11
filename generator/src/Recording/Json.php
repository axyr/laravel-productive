<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Reads decoded JSON:API documents from recordings defensively: anything malformed reads as empty.
 */
final class Json
{
    /**
     * A JSON object with string keys; a list, scalar or null reads as empty.
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        $map = [];

        foreach (is_array($value) && ! array_is_list($value) ? $value : [] as $key => $item) {
            // PHP stores a numeric key as int either way: the cast only tells static analysis.
            $map[(string) $key] = $item; // @pest-mutate-ignore
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * The resource objects of a document, primary and included, each with its type.
     *
     * @return list<array{string, array<string, mixed>}>
     */
    public static function resources(mixed $document): array
    {
        $data = self::map($document)['data'] ?? null;
        $candidates = [...(is_array($data) && array_is_list($data) ? $data : [$data]), ...self::list(self::map($document)['included'] ?? null)];

        return array_values(array_filter(array_map(self::resource(...), $candidates)));
    }

    /**
     * @return array{string, array<string, mixed>}|null
     */
    private static function resource(mixed $candidate): ?array
    {
        $resource = self::map($candidate);

        return is_string($resource['type'] ?? null) && isset($resource['id']) ? [$resource['type'], $resource] : null;
    }
}
