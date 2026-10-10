<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\RecordedTypes;

/**
 * @param  array<string, list<string>>  $attributes
 * @param  array<string, array{to_many: bool|null, types: list<string>}>  $relationships
 */
function recorded(array $attributes = [], array $relationships = []): RecordedTypes
{
    return new RecordedTypes(['types' => ['tasks' => ['attributes' => $attributes, 'relationships' => $relationships]]]);
}

function specAttribute(string $name, AttributeType $type): Attribute
{
    return new Attribute($name, $name, $type, 'From the spec.', required: true, enum: ['a'], items: AttributeType::String);
}

it('keeps the spec type when its reader reads every recorded value', function (AttributeType $spec, array $kinds) {
    $attribute = specAttribute('value', $spec);

    expect(recorded(['value' => $kinds])->attributes('tasks', ['value' => $attribute])['value'])->toBe($attribute);
})->with([
    'float field with ints' => [AttributeType::Float, ['int', 'float']],
    'int field with integer strings' => [AttributeType::Int, ['int', 'integer-string']],
    'string field with dates' => [AttributeType::String, ['date', 'date-time', 'decimal-string']],
    'date-time field with dates' => [AttributeType::DateTime, ['date']],
    'mixed field' => [AttributeType::Mixed, ['list', 'object', 'bool']],
    'only null and empty' => [AttributeType::String, ['null', 'empty']],
    'unknown kinds' => [AttributeType::String, ['nonsense']],
    'object field with lists' => [AttributeType::Object, ['list']],
]);

it('overrides the spec type when a recorded value is unreadable', function (AttributeType $spec, array $kinds, AttributeType $recorded) {
    $attribute = recorded(['value' => $kinds])->attributes('tasks', ['value' => specAttribute('value', $spec)])['value'];

    expect($attribute)->toEqual(new Attribute('value', 'value', $recorded, 'From the spec.', required: true, specType: $spec));
})->with([
    'bool sent for an int' => [AttributeType::Int, ['bool', 'null'], AttributeType::Bool],
    'float sent for an int widens' => [AttributeType::Int, ['int', 'float'], AttributeType::Float],
    'string sent for an int' => [AttributeType::Int, ['string'], AttributeType::String],
    'map sent for a string' => [AttributeType::String, ['object', 'empty'], AttributeType::Object],
    'date-time sent for a date' => [AttributeType::Date, ['date-time'], AttributeType::DateTime],
    'string sent for an object' => [AttributeType::Object, ['string'], AttributeType::String],
    'int sent for a bool' => [AttributeType::Bool, ['int'], AttributeType::Int],
    'list and map sent for a string' => [AttributeType::String, ['list', 'object'], AttributeType::Mixed],
]);

it('adds undocumented attributes with their recorded type', function () {
    $attributes = recorded(['level' => ['int', 'null'], 'type' => ['string'], 'notes' => ['null']])->attributes('tasks', []);

    expect($attributes)->toEqual([
        'level' => new Attribute('level', 'level', AttributeType::Int, 'Not in the spec: seen in recorded API responses.', undocumented: true),
        'type' => new Attribute('type', 'typeValue', AttributeType::String, 'Not in the spec: seen in recorded API responses.', undocumented: true),
        'notes' => new Attribute('notes', 'notes', AttributeType::Mixed, 'Not in the spec: seen in recorded API responses.', undocumented: true),
    ]);
});

it('leaves types that were not recorded alone', function () {
    $attribute = specAttribute('title', AttributeType::String);

    expect((new RecordedTypes())->attributes('tasks', ['title' => $attribute]))->toBe(['title' => $attribute])
        ->and(recorded(['title' => ['int']])->attributes('people', ['title' => $attribute]))->toBe(['title' => $attribute]);
});

it('adds recorded relationships the spec lacks, when their cardinality is known', function () {
    $spec = ['project' => new Relationship('project', false, 'projects')];
    $relationships = recorded(relationships: [
        'project' => ['to_many' => true, 'types' => ['people']],
        'todos' => ['to_many' => true, 'types' => ['todos']],
        'root_task' => ['to_many' => false, 'types' => ['tasks']],
        'owner' => ['to_many' => false, 'types' => ['people', 'users']],
        'meeting' => ['to_many' => false, 'types' => ['meetings']],
        'subtasks' => ['to_many' => null, 'types' => []],
    ])->relationships('tasks', $spec, ['projects', 'tasks', 'todos', 'people', 'users']);

    expect($relationships)->toEqual([
        'project' => new Relationship('project', false, 'projects'),
        'todos' => new Relationship('todos', true, 'todos', undocumented: true),
        'root_task' => new Relationship('root_task', false, 'tasks', undocumented: true),
        'owner' => new Relationship('owner', false, null, undocumented: true),
        'meeting' => new Relationship('meeting', false, null, undocumented: true),
    ]);
});

it('lists relationships recorded without data', function () {
    $recorded = new RecordedTypes(['types' => [
        'tasks' => ['relationships' => ['subtasks' => ['to_many' => null, 'types' => []], 'project' => ['to_many' => false, 'types' => ['projects']]]],
        'people' => ['relationships' => ['manager' => ['to_many' => null]]],
        'teams' => [],
    ]]);

    expect($recorded->untypedRelationships())->toBe(['tasks' => ['subtasks'], 'people' => ['manager']]);
});

it('exposes the statuses, rejected includes and recording date', function () {
    $recorded = new RecordedTypes([
        'recorded_on' => '2026-10-09',
        'statuses' => ['boards.index' => 404, 'tasks.index' => 200, 'odd' => '200'],
        'rejected_includes' => ['reports.task_reports.index' => ['board', 5, 'organization'], 'x' => 'organization'],
    ]);

    expect($recorded->statuses())->toBe(['boards.index' => 404, 'tasks.index' => 200])
        ->and($recorded->rejectedIncludes())->toBe(['reports.task_reports.index' => ['board', 'organization'], 'x' => []])
        ->and($recorded->recordedOn())->toBe('2026-10-09')
        ->and((new RecordedTypes())->recordedOn())->toBe('')
        ->and((new RecordedTypes())->statuses())->toBe([]);
});

it('reads the summary from a file, or nothing when there is none or it is not an object', function () {
    $path = tempnam(sys_get_temp_dir(), 'recorded');

    try {
        file_put_contents($path, json_encode(['recorded_on' => '2026-10-09']));
        expect(RecordedTypes::fromFile($path)->recordedOn())->toBe('2026-10-09')
            ->and(RecordedTypes::fromFile($path . '.missing')->recordedOn())->toBe('');

        file_put_contents($path, '"text"');
        expect(RecordedTypes::fromFile($path)->recordedOn())->toBe('');
    } finally {
        unlink($path);
    }
});

it('ignores malformed parts of the summary', function () {
    $recorded = new RecordedTypes(['recorded_on' => 5, 'types' => ['tasks' => ['attributes' => ['a' => 'int', 'b' => ['int', 7, 'nonsense']], 'relationships' => ['r' => 'junk', 's' => ['to_many' => 'yes', 'types' => 'tasks']]], 'teams' => 'junk']]);

    expect($recorded->recordedOn())->toBe('')
        ->and($recorded->hasType('teams'))->toBeTrue()
        ->and(array_map(fn(Attribute $attribute): string => $attribute->type->value, $recorded->attributes('tasks', [])))->toBe(['a' => 'mixed', 'b' => 'int'])
        ->and($recorded->untypedRelationships())->toBe(['tasks' => ['r', 's']]);
});
