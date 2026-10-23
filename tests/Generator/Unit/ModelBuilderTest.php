<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\ModelBuilder;
use Axyr\Productive\Generator\RecordedTypes;
use Axyr\Productive\Generator\RelationshipTypes;
use Axyr\Productive\Generator\SchemaReader;
use Axyr\Productive\Generator\Spec;

function modelBuilder(array $schemas = []): ModelBuilder
{
    $spec = new Spec(['components' => ['schemas' => $schemas]]);

    return new ModelBuilder($spec, new SchemaReader($spec), new RelationshipTypes([], [], ['people', 'tasks']));
}

it('builds a model from a single resource response', function () {
    $model = modelBuilder([
        'resource_task' => ['description' => "An assignable work item.\nMore.", 'properties' => ['title' => ['type' => 'string']]],
    ])->build('Task', 'tasks', ['properties' => ['data' => ['properties' => [
        'attributes' => ['properties' => ['title' => ['$ref' => '#/components/schemas/resource_task/properties/title'], 'closed' => []]],
        'relationships' => ['properties' => [
            'subscribers' => ['$ref' => '#/components/schemas/_collection_relationship'],
            'assignee' => ['$ref' => '#/components/schemas/_single_relationship'],
            'parent_task' => [],
        ]],
    ]]]], ['type' => 'tasks', 'attributes' => ['closed' => false, 'description' => 'x']]);

    expect($model->toArray())->toBe([
        'class' => 'Task',
        'type' => 'tasks',
        'description' => 'An assignable work item.',
        'attributes' => [
            ['name' => 'closed', 'property' => 'closed', 'type' => 'bool'],
            ['name' => 'description', 'property' => 'description', 'type' => 'string'],
            ['name' => 'title', 'property' => 'title', 'type' => 'string'],
        ],
        'relationships' => [
            ['name' => 'assignee', 'to_many' => false, 'target_type' => null],
            ['name' => 'parent_task', 'to_many' => false, 'target_type' => 'tasks'],
            ['name' => 'subscribers', 'to_many' => true, 'target_type' => null],
        ],
        'example' => ['closed' => false, 'description' => 'x'],
        'phantom' => [],
    ]);
});

it('leaves out example-only attributes a recording of the type never saw, and keeps them for a type not recorded', function () {
    $spec = new Spec(['components' => ['schemas' => []]]);
    $recorded = new RecordedTypes(['types' => ['webhooks' => ['attributes' => ['name' => ['string'], 'target_url' => ['string']], 'relationships' => []]]]);
    $builder = new ModelBuilder($spec, new SchemaReader($spec), new RelationshipTypes([], [], []), $recorded);
    $response = ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => ['name' => ['type' => 'string'], 'test' => ['type' => 'boolean']]]]]]];
    $example = ['attributes' => ['name' => 'Hook', 'url' => 'https://x', 'events' => ['task.created'], 'test' => true]];

    $webhook = $builder->build('Webhook', 'webhooks', $response, $example);
    $other = $builder->build('Other', 'others', $response, $example);

    expect(array_map(fn(Attribute $attribute): string => $attribute->name, $webhook->attributes))->toBe(['name', 'target_url', 'test'])
        ->and($webhook->phantom)->toBe(['url', 'events'])
        ->and($webhook->example)->toBe(['name' => 'Hook', 'test' => true])
        ->and(array_map(fn(Attribute $attribute): string => $attribute->name, $other->attributes))->toBe(['events', 'name', 'test', 'url'])
        ->and($other->phantom)->toBe([])
        ->and($other->example)->toBe($example['attributes']);
});

it('reads the item schema of a collection response', function () {
    $model = modelBuilder()->build('Row', 'rows', ['properties' => ['data' => ['type' => 'array', 'items' => ['properties' => ['attributes' => ['properties' => ['count' => ['type' => 'integer']]]]]]]], []);

    expect(array_map(fn(Attribute $attribute): string => $attribute->name, $model->attributes))->toBe(['count'])
        ->and($model->relationships)->toBe([])
        ->and($model->description)->toBe('');
});

it('has no description without a resource schema reference or a string description', function () {
    $schemas = ['resource_x' => ['description' => ['not', 'a string'], 'properties' => ['b' => []]]];
    $response = ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => [
        'a' => ['$ref' => '#/components/schemas/other/properties/a'],
        'b' => ['$ref' => '#/components/schemas/resource_x/properties/b'],
    ]]]]]];

    expect(modelBuilder($schemas + ['other' => ['properties' => ['a' => []]]])->build('X', 'xs', $response, [])->description)->toBe('');
});

it('exposes relationship definitions', function () {
    expect((new Relationship('assignee', false, 'people'))->toArray())->toBe(['name' => 'assignee', 'to_many' => false, 'target_type' => 'people']);
});

it('takes the first non-empty line of the resource description', function (array $resource, string $description) {
    $model = modelBuilder(['resource_x' => [...$resource, 'properties' => ['a' => []]]])->build('X', 'xs', ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => [
        'a' => ['$ref' => '#/components/schemas/resource_x/properties/a'],
    ]]]]]], []);

    expect($model->description)->toBe($description);
})->with([
    [['description' => "  Spaced.  \nSecond line."], 'Spaced.'],
    [[], ''],
]);
