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
        $enabled = array_values(ConfigMaps::strings(require $config . '/resources.php'));
        $removed = ConfigMaps::config(require $config . '/removed.php');
        $coverage = ConfigMaps::config(require $config . '/coverage.php');
        $recorded = RecordedTypes::fromFile($root . '/generator/recorded-types.json');

        return [
            ...(new CodeGenerator($api, $enabled, ConfigMaps::strings(require $config . '/type-aliases.php')))->files(),
            ReferenceEmitter::PATH => ReferenceEmitter::emit($api, $enabled),
            DifferencesEmitter::PATH => DifferencesEmitter::emit(
                $api,
                $enabled,
                $recorded,
                ['resources' => ConfigMaps::strings($removed['resources'] ?? []), 'relationships' => ConfigMaps::strings($removed['relationships'] ?? []), 'operations' => ConfigMaps::strings($removed['operations'] ?? [])],
            ),
            SpecReportEmitter::PATH => self::specReport($api, $enabled, $recorded, $config, $removed),
            CoverageEmitter::PATH => CoverageEmitter::emit(
                $api,
                $enabled,
                $recorded,
                ['skipped' => ConfigMaps::strings($coverage['skipped'] ?? []), 'plan_gated' => ConfigMaps::strings($coverage['plan_gated'] ?? [])],
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
            ConfigMaps::lists(require $config . '/required.php'),
            [...ConfigMaps::strings($removed['resources'] ?? []), ...ConfigMaps::strings($removed['relationships'] ?? []), ...ConfigMaps::strings($removed['operations'] ?? [])],
            ConfigMaps::recipes(require $config . '/recipes.php'),
            ConfigMaps::findings(require $config . '/spec-findings.php'),
        ))->emit();
    }
}
