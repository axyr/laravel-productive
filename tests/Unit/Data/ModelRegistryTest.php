<?php

declare(strict_types=1);

use Axyr\Productive\Data\GenericModel;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\JsonApi\ResourceObject;

it('maps the built-in types', function () {
    $registry = new ModelRegistry();

    expect($registry->classFor('tasks'))->toBe(Task::class)
        ->and($registry->classFor('time_entries'))->toBe(TimeEntry::class)
        ->and($registry->classFor('new_time_reports'))->toBe(TimeReport::class)
        ->and($registry->classFor('time_reports'))->toBe(TimeReport::class)
        ->and($registry->classFor('unknown'))->toBe(GenericModel::class);
});

it('accepts custom mappings that override the built-ins', function () {
    $registry = new ModelRegistry(['people' => TimeEntry::class, 'tasks' => GenericModel::class]);

    expect($registry->classFor('people'))->toBe(TimeEntry::class)
        ->and($registry->classFor('tasks'))->toBe(GenericModel::class)
        ->and($registry->hydrate(new ResourceObject('tasks', '1')))->toBeInstanceOf(GenericModel::class);
});
