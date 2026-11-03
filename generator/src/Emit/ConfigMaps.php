<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

/**
 * Reads the curated config files defensively: what is not of the expected shape is left out.
 */
final class ConfigMaps
{
    /**
     * @return array<array-key, mixed>
     */
    public static function config(mixed $config): array
    {
        return is_array($config) ? $config : [];
    }

    /**
     * Lists of strings by key, such as required.php's attributes by input class.
     *
     * @return array<string, list<string>>
     */
    public static function lists(mixed $config): array
    {
        $lists = [];

        foreach (self::config($config) as $key => $values) {
            // PHP keeps a numeric key an int whatever it is cast to; the cast only tells static analysis.
            $list = self::list($values);
            $lists[(string) $key] = $list; // @pest-mutate-ignore
        }

        return $lists;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function recipes(mixed $config): array
    {
        $recipes = [];

        foreach (self::config($config) as $name => $recipe) {
            if (is_array($recipe)) {
                // PHP keeps a numeric key an int whatever it is cast to; the cast only tells static analysis.
                $recipes[(string) $name] = $recipe; // @pest-mutate-ignore
            }
        }

        /** @var array<string, array<string, mixed>> $recipes */
        return $recipes;
    }

    /**
     * @return array{written: string, findings: array<string, list<array{endpoint: string, spec: string, api: string, reproduce: string}>>}
     */
    public static function findings(mixed $config): array
    {
        $config = self::config($config);
        $findings = [];

        foreach (self::config($config['findings'] ?? []) as $section => $entries) {
            // PHP keeps a numeric key an int whatever it is cast to; the cast only tells static analysis.
            $entries = array_values(array_map(self::finding(...), self::config($entries)));
            $findings[(string) $section] = $entries; // @pest-mutate-ignore
        }

        return ['written' => is_string($config['written'] ?? null) ? $config['written'] : '', 'findings' => $findings];
    }

    /**
     * @return array{endpoint: string, spec: string, api: string, reproduce: string}
     */
    private static function finding(mixed $entry): array
    {
        $entry = self::strings($entry);

        return ['endpoint' => $entry['endpoint'] ?? '', 'spec' => $entry['spec'] ?? '', 'api' => $entry['api'] ?? '', 'reproduce' => $entry['reproduce'] ?? ''];
    }

    /**
     * The string values of a config array, as a list.
     *
     * @return list<string>
     */
    public static function list(mixed $config): array
    {
        return array_values(self::strings($config));
    }

    /**
     * The string values of a config array, by key.
     *
     * @return array<string, string>
     */
    public static function strings(mixed $config): array
    {
        $strings = [];

        foreach (self::config($config) as $key => $value) {
            if (is_string($value)) {
                // PHP keeps a numeric key an int whatever it is cast to; the cast only tells static analysis.
                $strings[(string) $key] = $value; // @pest-mutate-ignore
            }
        }

        return $strings;
    }
}
