<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\GeneratedFiles;
use Axyr\Productive\Generator\Emit\ProjectFiles;

it('keeps the committed generated code in sync with the generator', function () {
    $root = dirname(__DIR__, 3);

    expect((new GeneratedFiles($root, ProjectFiles::build($root)))->check())->toBe([]);
})->group('drift');
