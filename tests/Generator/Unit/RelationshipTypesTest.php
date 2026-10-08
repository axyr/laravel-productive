<?php

declare(strict_types=1);

use Axyr\Productive\Generator\RelationshipTypes;
use Axyr\Productive\Generator\Spec;

it('resolves from examples, then overrides, then the name', function () {
    $types = new RelationshipTypes(
        ['tasks' => ['assignee' => 'people']],
        ['tasks.owner' => 'teams', 'owner' => 'people', 'template_object' => null],
        ['people', 'teams', 'tax_rates', 'tasks', 'projects'],
    );

    expect($types->resolve('tasks', 'assignee'))->toBe('people')
        ->and($types->resolve('tasks', 'owner'))->toBe('teams')
        ->and($types->resolve('deals', 'owner'))->toBe('people')
        ->and($types->resolve('tasks', 'template_object'))->toBeNull()
        ->and($types->resolve('tasks', 'project'))->toBe('projects')
        ->and($types->resolve('deals', 'default_tax_rate'))->toBe('tax_rates')
        ->and($types->resolve('tasks', 'parent_task'))->toBe('tasks')
        ->and($types->resolve('tasks', 'custom_field_people'))->toBe('people')
        ->and($types->resolve('tasks', 'vendor'))->toBeNull();
});

it('collects relationship types from example documents', function () {
    $spec = new Spec(['paths' => ['/api/v2/tasks/{id}' => ['get' => ['responses' => ['200' => ['content' => ['application/vnd.api+json' => ['schema' => ['example' => [
        'data' => ['type' => 'tasks', 'id' => '1', 'relationships' => [
            'assignee' => ['data' => ['type' => 'people', 'id' => '2']],
            'attachments' => ['data' => [['type' => 'attachments', 'id' => '3']]],
            'service' => ['data' => null],
            'project' => ['meta' => ['included' => false]],
        ]],
        'included' => [['type' => 'tasks', 'id' => '9', 'relationships' => ['assignee' => ['data' => ['type' => 'teams', 'id' => '1']]]], ['id' => 'no type']],
    ]]]]]]]]]]);

    $types = RelationshipTypes::fromExamples($spec, [], []);

    expect($types->resolve('tasks', 'assignee'))->toBe('people')
        ->and($types->resolve('tasks', 'attachments'))->toBe('attachments')
        ->and($types->resolve('tasks', 'service'))->toBeNull()
        ->and($types->resolve('tasks', 'project'))->toBeNull();
});
