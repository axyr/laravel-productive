<?php

declare(strict_types=1);

use Axyr\Productive\Data\InputData;
use Illuminate\Support\Str;

/**
 * @return list<class-string<InputData>>
 */
function inputClasses(): array
{
    $classes = [];

    foreach (glob(dirname(__DIR__, 3) . '/src/Data/Input/*.php') ?: [] as $file) {
        $classes[] = 'Axyr\\Productive\\Data\\Input\\' . basename($file, '.php');
    }

    return $classes;
}

/**
 * A distinct value the parameter accepts, which toAttributes() must pass through unchanged.
 */
function distinctValue(ReflectionParameter $parameter, int $seed): mixed
{
    $types = array_map(fn(ReflectionNamedType $type): string => $type->getName(), match (true) {
        $parameter->getType() instanceof ReflectionUnionType => $parameter->getType()->getTypes(),
        $parameter->getType() instanceof ReflectionNamedType => [$parameter->getType()],
        default => [],
    });

    return match (true) {
        in_array('string', $types, true), in_array('mixed', $types, true), $types === [] => 'value-' . $seed,
        in_array('int', $types, true) => 1000 + $seed,
        in_array('float', $types, true) => $seed + 0.5,
        in_array('bool', $types, true) => $seed % 2 === 0,
        in_array('array', $types, true) => ['item-' . $seed],
    };
}

it('maps every input field to its snake_case API attribute', function (string $class) {
    $parameters = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
    $arguments = [];
    $expected = [];

    foreach ($parameters as $index => $parameter) {
        $arguments[$parameter->getName()] = distinctValue($parameter, $index);
        $expected[Str::snake($parameter->getName())] = $arguments[$parameter->getName()];
    }

    expect($parameters)->not->toBeEmpty()
        ->and((new $class(...$arguments))->toAttributes())->toBe($expected);
})->with(inputClasses());
