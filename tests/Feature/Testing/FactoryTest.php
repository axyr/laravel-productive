<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\Testing\Factories\TaskFactory;
use Axyr\Productive\Testing\Factories\TimeEntryFactory;
use Axyr\Productive\Testing\Factories\TimeReportFactory;

it('makes models from realistic defaults', function () {
    $task = TaskFactory::new()->make();
    $entry = TimeEntryFactory::new()->make();
    $row = TimeReportFactory::new()->make();

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->title)->toBe('Implement user authentication')
        ->and($entry)->toBeInstanceOf(TimeEntry::class)
        ->and($entry->time)->toBe(480)
        ->and($row)->toBeInstanceOf(TimeReport::class)
        ->and($row->billableTime)->toBe(14400.0);
});

it('uses the attributes of Productive\'s own examples as defaults', function (string $factory) {
    $model = (new ReflectionClass($factory))->getShortName();
    $models = array_column(json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/generator/api.json'), true)['models'], null, 'class');
    $resource = $factory::new()->resource();
    $hydrated = $factory::new()->make();

    expect($resource['attributes'])->toBe(array_diff_key($models[substr($model, 0, -strlen('Factory'))]['example'], ['id' => true, 'type' => true]))
        ->and($resource['type'])->toBe($hydrated->type)
        ->and($hydrated->attributes())->toBe($resource['attributes']);
})->with(fn(): array => array_map(
    fn(string $file): string => 'Axyr\\Productive\\Testing\\Factories\\' . basename($file, '.php'),
    array_values(array_filter(glob(dirname(__DIR__, 3) . '/src/Testing/Factories/*Factory.php') ?: [], fn(string $file): bool => basename($file) !== 'Factory.php')),
));

it('merges consecutive states', function () {
    $resource = TaskFactory::new()->state(['title' => 'A'])->state(['private' => true])->resource();

    expect($resource['attributes']['title'])->toBe('A')
        ->and($resource['attributes']['private'])->toBeTrue();
});

it('generates positive numeric ids and casts given ids to strings', function () {
    $resource = TaskFactory::new()->resource();

    expect($resource['id'])->toBeString()
        ->and(ctype_digit($resource['id']))->toBeTrue()
        ->and(TaskFactory::new()->resource(['id' => 5])['id'])->toBe('5');
});

it('overrides attributes and ids', function () {
    $task = TaskFactory::new()->state(['private' => true])->make(['id' => 99, 'title' => 'Custom']);

    expect($task->id)->toBe('99')
        ->and($task->title)->toBe('Custom')
        ->and($task->private)->toBeTrue()
        ->and($task->attributes())->not->toHaveKey('id');
});

it('gives every resource a unique id', function () {
    $ids = array_map(fn(Task $task): string => $task->id, TaskFactory::new()->count(3)->makeMany());

    expect(array_unique($ids))->toHaveCount(3);
});

it('builds resource objects for fake responses', function () {
    $resource = TaskFactory::new()->relatedTo('project', 'projects', '6899')->resource(['id' => '1']);

    expect($resource['type'])->toBe('tasks')
        ->and($resource['id'])->toBe('1')
        ->and($resource['relationships'])->toBe(['project' => ['data' => ['type' => 'projects', 'id' => '6899']]])
        ->and(TaskFactory::new()->count(0)->resources())->toHaveCount(1)
        ->and(TaskFactory::new()->resource(['id' => ['not scalar']])['id'])->toBe('');
});

it('does not share state between factory instances', function () {
    $base = TaskFactory::new();
    $base->state(['title' => 'Changed'])->count(5)->relatedTo('project', 'projects', '1');

    expect($base->make()->title)->toBe('Implement user authentication')
        ->and($base->resources())->toHaveCount(1)
        ->and($base->resource())->not->toHaveKey('relationships');
});
