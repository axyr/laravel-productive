<?php

declare(strict_types=1);

use Axyr\Productive\Generator\InputBuilder;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\SchemaReader;
use Axyr\Productive\Generator\Spec;

function inputBuilder(array $components = []): InputBuilder
{
    $spec = new Spec(['components' => $components]);

    return new InputBuilder($spec, new SchemaReader($spec));
}

function body(array $data): array
{
    return ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => $data]]]]];
}

function names(array $attributes): array
{
    return array_map(fn(Attribute $attribute): string => $attribute->name, $attributes);
}

it('has no input without a body or attributes', function () {
    expect(inputBuilder()->build([], 'X'))->toBeNull()
        ->and(inputBuilder()->build(['requestBody' => body(['properties' => []])], 'X'))->toBeNull()
        ->and(inputBuilder()->build(['requestBody' => ['content' => []]], 'X'))->toBeNull();
});

it('orders required attributes first, then the rest by name', function () {
    $input = inputBuilder(['requestBodies' => ['task' => body(['properties' => ['attributes' => [
        'properties' => ['zeta' => [], 'title' => [], 'alpha' => [], 'project_id' => []],
        'required' => ['title', 'project_id', 'missing'],
    ]]])]])->build(['requestBody' => ['$ref' => '#/components/requestBodies/task']], 'CreateTaskData');

    expect($input?->class)->toBe('CreateTaskData')
        ->and($input?->requestBody)->toBe('task')
        ->and(names($input->attributes))->toBe(['title', 'project_id', 'alpha', 'zeta'])
        ->and(array_map(fn(Attribute $attribute): bool => $attribute->required, $input->attributes))->toBe([true, true, false, false]);
});

it('ignores the required list for updates', function () {
    $input = inputBuilder()->build(['requestBody' => body(['properties' => ['attributes' => ['properties' => ['b' => [], 'a' => []], 'required' => ['b']]]])], 'U', allOptional: true);

    expect(names($input->attributes))->toBe(['a', 'b'])
        ->and($input->requestBody)->toBe('');
});

it('reads the item attributes of bulk bodies', function () {
    $input = inputBuilder()->build(['requestBody' => body(['type' => 'array', 'items' => ['properties' => ['attributes' => ['properties' => ['time' => ['type' => 'integer']]]]]])], 'B');

    expect(names($input->attributes))->toBe(['time'])
        ->and($input->attributes[0]->required)->toBeFalse();
});

it('stops on a malformed required list', function () {
    inputBuilder()->build(['requestBody' => body(['properties' => ['attributes' => ['properties' => ['a' => []], 'required' => ['a', 7]]]])], 'X');
})->throws(RuntimeException::class, 'Required attributes must only contain strings, int found.');

it('names the input after the request body it references', function () {
    $input = inputBuilder(['requestBodies' => ['time_entry' => body(['properties' => ['attributes' => ['properties' => ['a' => []]]]])]])
        ->build(['requestBody' => ['$ref' => '#/components/requestBodies/time_entry']], 'X');

    expect($input?->requestBody)->toBe('time_entry')
        ->and($input?->toArray())->toBe(['class' => 'X', 'request_body' => 'time_entry', 'attributes' => [['name' => 'a', 'property' => 'a', 'type' => 'mixed']]]);
});
