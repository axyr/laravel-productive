<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Picks, per type, the fewest recorded resources that together show every kind of value each
 * attribute had, and every relationship with data. Lists and maps inside attribute values are cut
 * to a few entries: the hydration test needs the shapes, not the volume.
 */
final class FixtureSelector
{
    public const int LIST_ITEMS = 5;

    /**
     * @param  array<string, array<string, mixed>>  $recordings  Recordings by name, as `composer record` writes them.
     * @return array<string, list<array<string, mixed>>>  Resource objects by type, sorted by type.
     */
    public static function select(array $recordings): array
    {
        ksort($recordings);
        $selected = [];

        foreach (self::candidates($recordings) as $type => $resources) {
            $selected[$type] = array_map(self::shortenAttributes(...), self::cover($resources));
        }

        return $selected;
    }

    /**
     * @param  array<string, array<string, mixed>>  $recordings
     * @return array<string, list<array<string, mixed>>>  Every resource of every successful call, by type.
     */
    private static function candidates(array $recordings): array
    {
        $candidates = [];

        foreach ($recordings as $recording) {
            foreach (Json::resources(($recording['status'] ?? null) === 200 ? $recording['body'] ?? null : null) as [$type, $resource]) {
                $candidates[$type][] = $resource;
            }
        }

        ksort($candidates);

        return $candidates;
    }

    /**
     * Greedy cover: keep a resource only when it shows a feature no kept resource shows yet.
     *
     * @param  list<array<string, mixed>>  $resources
     * @return list<array<string, mixed>>
     */
    private static function cover(array $resources): array
    {
        $covered = [];
        $kept = [];

        foreach ($resources as $resource) {
            $new = array_diff(self::features($resource), $covered);

            if ($new !== [] || $kept === []) {
                $kept[] = $resource;
                $covered = [...$covered, ...$new];
            }
        }

        return $kept;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<string>  "attribute:kind" for every attribute, the bare name for every relationship with data.
     */
    private static function features(array $resource): array
    {
        $attributes = Json::map($resource['attributes'] ?? null);
        $relationships = array_filter(Json::map($resource['relationships'] ?? null), fn(mixed $relationship): bool => is_array(Json::map($relationship)['data'] ?? null));

        return [
            ...array_map(fn(string $name, mixed $value): string => sprintf('%s:%s', $name, ValueKind::detect($value)->value), array_keys($attributes), $attributes),
            ...array_keys($relationships),
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private static function shortenAttributes(array $resource): array
    {
        if (is_array($resource['attributes'] ?? null)) {
            $resource['attributes'] = array_map(self::shorten(...), $resource['attributes']);
        }

        return $resource;
    }

    /**
     * Cuts lists and maps inside an attribute value to a few entries, at every depth.
     */
    private static function shorten(mixed $value): mixed
    {
        return is_array($value) ? array_map(self::shorten(...), array_slice($value, 0, self::LIST_ITEMS, true)) : $value;
    }
}
