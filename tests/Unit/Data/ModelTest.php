<?php

declare(strict_types=1);

use Axyr\Productive\Data\Attributes;
use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Exceptions\RelationshipNotIncludedException;
use Axyr\Productive\JsonApi\Document;

function taskDocument(): Document
{
    return Document::fromArray([
        'data' => [
            'type' => 'tasks',
            'id' => '1',
            'attributes' => ['title' => 'Parent', 'unknown_new_field' => ['x' => 1]],
            'relationships' => [
                'assignee' => ['data' => ['type' => 'people', 'id' => '12']],
                'parent_task' => ['data' => ['type' => 'tasks', 'id' => '2']],
                'service' => ['data' => null],
                'attachments' => ['data' => [['type' => 'attachments', 'id' => '7'], ['type' => 'attachments', 'id' => '8']]],
                'custom_field_people' => ['data' => [['type' => 'people', 'id' => '12'], ['type' => 'tasks', 'id' => '2']]],
                'project' => ['meta' => ['included' => false]],
                'task_list' => ['data' => ['type' => 'task_lists', 'id' => '99']],
            ],
            'meta' => ['permissions' => ['edit' => true]],
        ],
        'included' => [
            ['type' => 'people', 'id' => '12', 'attributes' => ['first_name' => 'Ada']],
            ['type' => 'tasks', 'id' => '2', 'attributes' => ['title' => 'Grandparent'], 'relationships' => ['parent_task' => ['data' => ['type' => 'tasks', 'id' => '1']]]],
            ['type' => 'attachments', 'id' => '7', 'attributes' => []],
            ['type' => 'attachments', 'id' => '8', 'attributes' => []],
        ],
    ]);
}

function task(): Task
{
    $document = taskDocument();
    $model = (new ModelRegistry())->hydrate($document->resource(), $document->index());
    assert($model instanceof Task);

    return $model;
}

it('exposes id, type and raw data', function () {
    $task = task();

    expect($task->id)->toBe('1')
        ->and($task->type)->toBe('tasks')
        ->and($task->title)->toBe('Parent')
        ->and($task->attribute('unknown_new_field'))->toBe(['x' => 1])
        ->and($task->attribute('missing'))->toBeNull()
        ->and($task->attributes())->toBe(['title' => 'Parent', 'unknown_new_field' => ['x' => 1]])
        ->and($task->meta())->toBe(['permissions' => ['edit' => true]])
        ->and($task->resource()->id)->toBe('1');
});

it('reads relationship ids without includes', function () {
    $task = task();

    expect($task->relationshipId('assignee'))->toBe('12')
        ->and($task->relationshipId('service'))->toBeNull()
        ->and($task->relationshipIds('attachments'))->toBe(['7', '8'])
        ->and(fn() => $task->relationshipId('project'))->toThrow(RelationshipNotIncludedException::class)
        ->and(fn() => $task->relationshipId('nonexistent'))->toThrow(RelationshipNotIncludedException::class);
});

it('hydrates included relationships, including cycles', function () {
    $task = task();
    $parent = $task->parentTask();

    expect($parent)->toBeInstanceOf(Task::class)
        ->and($parent?->title)->toBe('Grandparent')
        ->and($parent?->parentTask()?->title)->toBe('Parent')
        ->and($task->assignee())->toBeInstanceOf(Axyr\Productive\Data\Models\Person::class)
        ->and($task->assignee()?->attribute('first_name'))->toBe('Ada')
        ->and($task->service())->toBeNull();
});

it('hydrates to-many relationships', function () {
    $attachments = task()->attachments();

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0]->id)->toBe('7')
        ->and($attachments[1]->id)->toBe('8');
});

it('returns any relationship generically', function () {
    $task = task();

    expect($task->related('assignee'))->toBeInstanceOf(Model::class)
        ->and($task->related('service'))->toBeNull()
        ->and($task->related('attachments'))->toBeArray()->toHaveCount(2);
});

it('returns every related model, whatever its type, through related()', function () {
    $related = task()->related('custom_field_people');

    expect($related)->toHaveCount(2)
        ->and($related[1])->toBeInstanceOf(Task::class);
});

it('fails loudly when a typed to-one accessor finds another type', function () {
    $repeated = Document::fromArray(['data' => ['type' => 'tasks', 'id' => '5', 'relationships' => ['repeated_task' => ['data' => ['type' => 'people', 'id' => '1']]]], 'included' => [['type' => 'people', 'id' => '1']]]);
    $model = (new ModelRegistry())->hydrate($repeated->resource(), $repeated->index());
    assert($model instanceof Task);

    $model->repeatedTask();
})->throws(InvalidResponseException::class, 'The "repeated_task" relationship of this tasks resource was expected to contain Axyr\Productive\Data\Models\Task, but contains "people".');

it('fails loudly when a to-one accessor receives a list', function () {
    $document = Document::fromArray(['data' => ['type' => 'tasks', 'id' => '5', 'relationships' => ['parent_task' => ['data' => [['type' => 'tasks', 'id' => '6']]]]], 'included' => [['type' => 'tasks', 'id' => '6']]]);
    $model = (new ModelRegistry())->hydrate($document->resource(), $document->index());
    assert($model instanceof Task);

    $model->parentTask();
})->throws(InvalidResponseException::class, 'but contains a list.');

it('throws a helpful error for relationships that were not included', function () {
    $task = task();

    expect(fn() => $task->project())->toThrow(RelationshipNotIncludedException::class, '->include(\'project\')')
        ->and(fn() => $task->taskList())->toThrow(RelationshipNotIncludedException::class, '"task_list"')
        ->and(fn() => $task->organization())->toThrow(RelationshipNotIncludedException::class);
});

it('serializes to the raw resource shape', function () {
    $model = new TimeEntry(Document::fromArray(['data' => ['type' => 'time_entries', 'id' => '3', 'attributes' => ['time' => 60]]])->resource());

    expect($model->toArray())->toBe(['id' => '3', 'type' => 'time_entries', 'attributes' => ['time' => 60]])
        ->and(json_encode($model))->toBe('{"id":"3","type":"time_entries","attributes":{"time":60}}');
});

final readonly class ModelTestProject extends Model
{
    public const string TYPE = 'projects';

    protected function hydrate(Attributes $attributes): void {}

    /**
     * @return list<Task>
     */
    public function tasks(): array
    {
        return $this->hasMany('tasks', Task::class);
    }
}

it('returns typed models from typed to-many accessors', function () {
    $document = Document::fromArray([
        'data' => ['type' => 'projects', 'id' => '1', 'relationships' => ['tasks' => ['data' => [
            ['type' => 'tasks', 'id' => '2'],
            ['type' => 'tasks', 'id' => '3'],
        ]]]],
        'included' => [['type' => 'tasks', 'id' => '2'], ['type' => 'tasks', 'id' => '3']],
    ]);
    $project = new ModelTestProject($document->resource(), $document->index());
    $tasks = $project->tasks();

    expect(array_keys($tasks))->toBe([0, 1])
        ->and($tasks[0])->toBeInstanceOf(Task::class)
        ->and($tasks[0]->id)->toBe('2')
        ->and($tasks[1]->id)->toBe('3');
});

it('fails loudly when a typed to-many accessor finds another type', function () {
    $document = Document::fromArray([
        'data' => ['type' => 'projects', 'id' => '1', 'relationships' => ['tasks' => ['data' => [['type' => 'tasks', 'id' => '2'], ['type' => 'people', 'id' => '1']]]]],
        'included' => [['type' => 'tasks', 'id' => '2'], ['type' => 'people', 'id' => '1']],
    ]);

    (new ModelTestProject($document->resource(), $document->index()))->tasks();
})->throws(InvalidResponseException::class, 'contains "people"');
