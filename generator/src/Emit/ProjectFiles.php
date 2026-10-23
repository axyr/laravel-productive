<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

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
            CoverageEmitter::PATH => CoverageEmitter::emit(
                $api,
                $enabled,
                $recorded,
                ['skipped' => self::stringMap($coverage['skipped'] ?? []), 'plan_gated' => self::stringMap($coverage['plan_gated'] ?? [])],
            ),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function config(mixed $config): array
    {
        return is_array($config) ? $config : [];
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
