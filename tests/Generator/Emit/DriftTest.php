<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\CodeGenerator;
use Axyr\Productive\Generator\Emit\GeneratedFiles;
use Axyr\Productive\Generator\IrBuilder;
use Tests\Support\SpecExamples;

it('keeps the committed generated code in sync with the generator', function () {
    $root = dirname(__DIR__, 3);
    $config = $root . '/generator/config';
    $api = IrBuilder::fromFiles(SpecExamples::path(), $config)->build();
    $files = (new CodeGenerator($api, require $config . '/resources.php', require $config . '/type-aliases.php'))->files();

    expect((new GeneratedFiles($root, $files))->check())->toBe([]);
})->group('drift');
