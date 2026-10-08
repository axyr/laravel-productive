<?php

declare(strict_types=1);

use Axyr\Productive\Generator\ClassifiedPath;
use Axyr\Productive\Generator\ParameterValues;
use Axyr\Productive\Generator\Spec;

function indexEntries(array $parameters, string $method = 'GET', bool $member = false, array $actions = []): array
{
    return [
        [new ClassifiedPath('tasks/{id}', 'tasks', true, ['id'], []), 'GET', ['parameters' => []]],
        [new ClassifiedPath('tasks', 'tasks', $member, [], $actions), $method, ['parameters' => $parameters]],
    ];
}

it('reads sort values from an array schema and group values from a string schema', function () {
    $spec = new Spec(['components' => ['parameters' => ['sort_task' => ['name' => 'sort', 'schema' => ['type' => 'array', 'items' => ['enum' => ['title', '-title']]]]]]]);
    $entries = indexEntries([['$ref' => '#/components/parameters/sort_task'], ['name' => 'group', 'schema' => ['enum' => ['person']]], 'odd']);

    expect(ParameterValues::forIndex($spec, $entries, 'sort'))->toBe(['title', '-title'])
        ->and(ParameterValues::forIndex($spec, $entries, 'group'))->toBe(['person'])
        ->and(ParameterValues::forIndex($spec, $entries, 'filter'))->toBe([]);
});

it('only reads the index endpoint', function () {
    $spec = new Spec([]);
    $parameters = [['name' => 'sort', 'schema' => ['enum' => ['title']]]];

    expect(ParameterValues::forIndex($spec, indexEntries($parameters, 'POST'), 'sort'))->toBe([])
        ->and(ParameterValues::forIndex($spec, indexEntries($parameters, 'GET', true), 'sort'))->toBe([])
        ->and(ParameterValues::forIndex($spec, indexEntries($parameters, 'GET', false, ['copy']), 'sort'))->toBe([])
        ->and(ParameterValues::forIndex($spec, [[new ClassifiedPath('tasks', 'tasks', false, [], []), 'GET', ['parameters' => 'x']]], 'sort'))->toBe([])
        ->and(ParameterValues::forIndex($spec, [[new ClassifiedPath('tasks', 'tasks', false, [], []), 'GET', ['parameters' => [['name' => 'sort', 'schema' => ['enum' => 'x']]]]]], 'sort'))->toBe([]);
});

it('stops on non-string sort values', function () {
    ParameterValues::forIndex(new Spec([]), indexEntries([['name' => 'sort', 'schema' => ['title' => 'sort task', 'enum' => ['a', 5]]]]), 'sort');
})->throws(RuntimeException::class, 'Sort task values must only contain strings, int found.');

it('names the parameter in the error when the schema has no title', function () {
    ParameterValues::forIndex(new Spec([]), indexEntries([['name' => 'group', 'schema' => ['enum' => [1.5]]]]), 'group');
})->throws(RuntimeException::class, 'Parameter values must only contain strings, float found.');
