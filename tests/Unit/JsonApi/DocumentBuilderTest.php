<?php

declare(strict_types=1);

use Axyr\Productive\JsonApi\DocumentBuilder;
use Axyr\Productive\JsonApi\ResourceIdentifier;

it('builds a create document', function () {
    expect(DocumentBuilder::resource('tasks', ['title' => 'A']))->toBe([
        'data' => ['type' => 'tasks', 'attributes' => ['title' => 'A']],
    ]);
});

it('builds an update document with id and relationships', function () {
    expect(DocumentBuilder::resource('tasks', ['title' => 'A'], '5', [
        'assignee' => new ResourceIdentifier('people', '12'),
        'subscribers' => [new ResourceIdentifier('people', '1'), new ResourceIdentifier('people', '2')],
        'service' => null,
    ]))->toBe([
        'data' => [
            'type' => 'tasks',
            'id' => '5',
            'attributes' => ['title' => 'A'],
            'relationships' => [
                'assignee' => ['data' => ['type' => 'people', 'id' => '12']],
                'subscribers' => ['data' => [['type' => 'people', 'id' => '1'], ['type' => 'people', 'id' => '2']]],
                'service' => ['data' => null],
            ],
        ],
    ]);
});

it('leaves out empty attributes', function () {
    expect(DocumentBuilder::resource('tasks', [], '5'))->toBe(['data' => ['type' => 'tasks', 'id' => '5']]);
});

it('builds a bulk document', function () {
    expect(DocumentBuilder::bulk('time_entries', [
        ['attributes' => ['time' => 60]],
        ['id' => '7', 'attributes' => ['note' => 'x']],
        ['id' => '8'],
    ]))->toBe([
        'data' => [
            ['type' => 'time_entries', 'attributes' => ['time' => 60]],
            ['type' => 'time_entries', 'id' => '7', 'attributes' => ['note' => 'x']],
            ['type' => 'time_entries', 'id' => '8'],
        ],
    ]);
});

it('builds an identifier list', function () {
    expect(DocumentBuilder::identifiers('time_entries', ['1', '2']))->toBe([
        'data' => [['type' => 'time_entries', 'id' => '1'], ['type' => 'time_entries', 'id' => '2']],
    ]);
});
