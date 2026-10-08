<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\SchemaReader;
use Axyr\Productive\Generator\Spec;

it('maps schema types and formats', function (array $schema, AttributeType $type) {
    expect(SchemaReader::type('field', $schema))->toBe($type);
})->with([
    [['type' => 'integer'], AttributeType::Int],
    [['type' => 'number'], AttributeType::Float],
    [['type' => 'boolean'], AttributeType::Bool],
    [['type' => 'object'], AttributeType::Object],
    [['type' => 'array'], AttributeType::List],
    [['type' => 'string'], AttributeType::String],
    [['type' => 'string', 'format' => 'date'], AttributeType::Date],
    [['type' => 'string', 'format' => 'date-time'], AttributeType::DateTime],
    [['type' => 'string', 'format' => 'time'], AttributeType::Time],
    [['type' => 'string', 'format' => 'email'], AttributeType::String],
]);

it('infers untyped properties from the example, then from the name', function (string $name, mixed $example, AttributeType $type) {
    expect(SchemaReader::type($name, [], $example))->toBe($type);
})->with([
    ['a', true, AttributeType::Bool],
    ['a', 3, AttributeType::Int],
    ['a', 1.5, AttributeType::Float],
    ['a', 'x', AttributeType::String],
    ['a', [1, 2], AttributeType::List],
    ['a', ['k' => 1], AttributeType::Object],
    ['approved_at', null, AttributeType::DateTime],
    ['started_at', '2026-03-15T09:00:00+00:00', AttributeType::DateTime],
    ['currency', null, AttributeType::String],
    ['currency_normalized', null, AttributeType::String],
    ['currency_rate', 1.5, AttributeType::Float],
    ['currencyless', null, AttributeType::Mixed],
    ['cost', null, AttributeType::Mixed],
]);

it('reads attributes from properties and examples', function () {
    $spec = new Spec(['components' => ['schemas' => ['title' => ['type' => 'string', 'description' => "Name.\nMore text */", 'enum' => ['a', 'b', 1.5, 2]]]]]);
    $reader = new SchemaReader($spec, ['assignee_id' => 'Fixed description.']);

    $attributes = $reader->attributes(
        ['title' => ['$ref' => '#/components/schemas/title'], 'assignee_id' => ['type' => 'integer', 'description' => 'Filter by assignee.'], 'note' => ['description' => 'Has */ in it']],
        ['description' => 'Only in example'],
        ['assignee_id'],
    );

    expect(array_keys($attributes))->toBe(['title', 'assignee_id', 'note', 'description'])
        ->and($attributes['title']->description)->toBe('Name.')
        ->and($attributes['title']->enum)->toBe(['a', 'b', 2])
        ->and($attributes['title']->required)->toBeFalse()
        ->and($attributes['assignee_id']->description)->toBe('Fixed description.')
        ->and($attributes['assignee_id']->required)->toBeTrue()
        ->and($attributes['assignee_id']->property)->toBe('assigneeId')
        ->and($attributes['note']->description)->toBe('Has * / in it')
        ->and($attributes['note']->type)->toBe(AttributeType::Mixed)
        ->and($attributes['description']->type)->toBe(AttributeType::String)
        ->and($attributes['description']->description)->toBe('');
});

it('renames properties that would shadow model members', function () {
    expect(SchemaReader::property('type'))->toBe('typeValue')
        ->and(SchemaReader::property('id'))->toBe('idValue')
        ->and(SchemaReader::property('type_id'))->toBe('typeId');
});

it('ignores a non-string description and a non-list enum', function () {
    $attributes = (new SchemaReader(new Spec([])))->attributes(['a' => ['description' => ['x'], 'enum' => 'x']]);

    expect($attributes['a']->description)->toBe('')
        ->and($attributes['a']->enum)->toBe([]);
});

it('reads numeric property names and trims descriptions', function () {
    $attributes = (new SchemaReader(new Spec([])))->attributes(['42' => ['description' => "\n  First line.  \nSecond line."]]);

    expect($attributes['42']->name)->toBe('42')
        ->and($attributes['42']->description)->toBe('First line.');
});
