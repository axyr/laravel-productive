<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\IrBuilder;
use Axyr\Productive\Generator\RecordedTypes;

/**
 * Every file `composer generate` writes for this project, from the vendored spec, the curated
 * config and the recorded types.
 */
final class ProjectFiles
{
    /**
     * @return array<string, string>  Contents by path relative to the project root.
     */
    public static function build(string $root): array
    {
        $config = $root . '/generator/config';
        $api = IrBuilder::fromFiles($root . '/resources/openapi/productive.json', $config)->build();
        $enabled = array_values(self::stringMap(require $config . '/resources.php'));
        $removed = self::config(require $config . '/removed.php');
        $coverage = self::config(require $config . '/coverage.php');
        $recorded = RecordedTypes::fromFile($root . '/generator/recorded-types.json');

        return [
            ...(new CodeGenerator($api, $enabled, self::stringMap(require $config . '/type-aliases.php')))->files(),
            ReferenceEmitter::PATH => ReferenceEmitter::emit($api, $enabled),
            DifferencesEmitter::PATH => DifferencesEmitter::emit(
                $api,
                $enabled,
                $recorded,
                ['resources' => self::stringMap($removed['resources'] ?? []), 'relationships' => self::stringMap($removed['relationships'] ?? [])],
            ),
            SpecReportEmitter::PATH => self::specReport($api, $enabled, $recorded, $config, $removed),
            CoverageEmitter::PATH => CoverageEmitter::emit(
                $api,
                $enabled,
                $recorded,
                ['skipped' => self::stringMap($coverage['skipped'] ?? []), 'plan_gated' => self::stringMap($coverage['plan_gated'] ?? [])],
            ),
        ];
    }

    /**
     * @param  list<string>  $enabled
     * @param  array<array-key, mixed>  $removed
     */
    private static function specReport(Api $api, array $enabled, RecordedTypes $recorded, string $config, array $removed): string
    {
        return (new SpecReportEmitter(
            $api,
            $enabled,
            $recorded,
            self::listMap(require $config . '/required.php'),
            [...self::stringMap($removed['resources'] ?? []), ...self::stringMap($removed['relationships'] ?? [])],
            self::recipes(require $config . '/recipes.php'),
            self::findings(require $config . '/spec-findings.php'),
        ))->emit();
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function config(mixed $config): array
    {
        return is_array($config) ? $config : [];
    }

    /**
     * Lists of strings by key, such as required.php's attributes by input class.
     *
     * @return array<string, list<string>>
     */
    private static function listMap(mixed $config): array
    {
        $lists = [];

        foreach (self::config($config) as $key => $values) {
            $lists[(string) $key] = array_values(self::stringMap($values));
        }

        return $lists;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function recipes(mixed $config): array
    {
        $recipes = [];

        foreach (self::config($config) as $name => $recipe) {
            if (is_array($recipe)) {
                $recipes[(string) $name] = $recipe;
            }
        }

        /** @var array<string, array<string, mixed>> $recipes */
        return $recipes;
    }

    /**
     * @return array{written: string, findings: array<string, list<array{endpoint: string, spec: string, api: string, reproduce: string}>>}
     */
    private static function findings(mixed $config): array
    {
        $config = self::config($config);
        $findings = [];

        foreach (self::config($config['findings'] ?? []) as $section => $entries) {
            $findings[(string) $section] = array_values(array_map(self::finding(...), self::config($entries)));
        }

        return ['written' => is_string($config['written'] ?? null) ? $config['written'] : '', 'findings' => $findings];
    }

    /**
     * @return array{endpoint: string, spec: string, api: string, reproduce: string}
     */
    private static function finding(mixed $entry): array
    {
        $entry = self::stringMap($entry);

        return ['endpoint' => $entry['endpoint'] ?? '', 'spec' => $entry['spec'] ?? '', 'api' => $entry['api'] ?? '', 'reproduce' => $entry['reproduce'] ?? ''];
    }

    /**
     * The string values of a config array, by key.
     *
     * @return array<string, string>
     */
    private static function stringMap(mixed $config): array
    {
        $strings = [];

        foreach (self::config($config) as $key => $value) {
            if (is_string($value)) {
                $strings[(string) $key] = $value;
            }
        }

        return $strings;
    }
}
