<?php

declare(strict_types=1);

use Axyr\Productive\Generator\ExampleIndex;
use Axyr\Productive\Generator\Spec;

function exampleResponse(mixed $example): array
{
    return ['content' => ['application/vnd.api+json' => ['schema' => ['example' => $example]], 'any' => ['schema' => []]]];
}

it('lists every resource object in the examples', function () {
    $spec = new Spec(['paths' => [
        '/api/v2/tasks' => ['get' => ['responses' => [
            '200' => exampleResponse(['data' => [['type' => 'tasks', 'id' => '1'], ['type' => 'tasks', 'id' => '2']], 'included' => [['type' => 'people', 'id' => '3']]]),
            '400' => exampleResponse('not a document'),
        ]]],
        '/api/v2/tasks/{id}' => ['get' => ['responses' => ['200' => exampleResponse(['data' => ['type' => 'tasks', 'id' => '4'], 'included' => 'x'])]]],
        '/api/v2/people/{id}' => ['get' => ['responses' => ['200' => exampleResponse(['data' => ['id' => 'untyped'], 'included' => ['scalar', ['type' => 'people', 'id' => '5']]])]]],
    ]]);

    expect(array_map(fn(array $object): string => $object['id'], ExampleIndex::resourceObjects($spec)))->toBe(['1', '2', '3', '4', '5']);
});

it('finds the first resource of a response example', function () {
    $spec = new Spec([]);

    expect(ExampleIndex::firstResource($spec, exampleResponse(['data' => [['type' => 'tasks', 'id' => '1']]]))['id'])->toBe('1')
        ->and(ExampleIndex::firstResource($spec, exampleResponse(['data' => ['type' => 'tasks', 'id' => '2']]))['id'])->toBe('2')
        ->and(ExampleIndex::firstResource($spec, exampleResponse(['data' => []])))->toBe([])
        ->and(ExampleIndex::firstResource($spec, exampleResponse(['meta' => []])))->toBe([])
        ->and(ExampleIndex::firstResource($spec, []))->toBe([]);
});

it('looks past examples without data', function () {
    $response = ['content' => [
        'application/json' => ['schema' => ['example' => ['meta' => []]]],
        'application/vnd.api+json' => ['schema' => ['example' => ['data' => ['type' => 'tasks', 'id' => '7']]]],
    ]];

    expect(ExampleIndex::firstResource(new Spec([]), $response)['id'])->toBe('7');
});
