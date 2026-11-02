<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\CoverageEmitter;
use Axyr\Productive\Generator\Emit\DifferencesEmitter;
use Axyr\Productive\Generator\Emit\ProjectFiles;
use Axyr\Productive\Generator\Emit\ReferenceEmitter;
use Axyr\Productive\Generator\Emit\SpecReportEmitter;

// The drift check compares every generated file; this pins the docs from the committed config
// without it, so mutation testing, which leaves the drift group out, sees what feeds them.
it('builds the generated docs from the committed config', function (string $path) {
    $root = dirname(__DIR__, 3);

    expect(ProjectFiles::build($root)[$path])->toBe(file_get_contents($root . '/' . $path));
})->with([ReferenceEmitter::PATH, DifferencesEmitter::PATH, SpecReportEmitter::PATH, CoverageEmitter::PATH]);
