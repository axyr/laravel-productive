<?php

declare(strict_types=1);

use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\JsonApi\Document;
use Illuminate\Support\Str;

/**
 * Every generated model class.
 *
 * @return list<class-string>
 */
function generatedModels(): array
{
    return array_map(
        fn(string $file): string => 'Axyr\\Productive\\Data\\Models\\' . basename($file, '.php'),
        glob(dirname(__DIR__, 4) . '/src/Data/Models/*.php') ?: [],
    );
}

/**
 * The model class an accessor returns: its return type, or the item class of `@return list<X>`.
 */
function accessorTarget(ReflectionMethod $method): string
{
    $type = ltrim((string) $method->getReturnType(), '?');

    if ($type !== 'array') {
        return $type;
    }

    preg_match('/@return list<(\w+)>/', (string) $method->getDocComment(), $match);
    $class = 'Axyr\\Productive\\Data\\Models\\' . ($match[1] ?? 'Model');

    return class_exists($class) ? $class : Model::class;
}

/**
 * @return list<ReflectionMethod>
 */
function relationshipAccessors(string $class): array
{
    $reflection = new ReflectionClass($class);

    return array_values(array_filter(
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        fn(ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && ! $method->isStatic(),
    ));
}

it('resolves every relationship accessor by its snake_case name', function (string $class) {
    $accessors = relationshipAccessors($class);
    $relationships = [];
    $included = [];

    foreach ($accessors as $index => $method) {
        $returnType = accessorTarget($method);
        $type = is_subclass_of($returnType, Model::class) ? $returnType::TYPE : 'related_' . $index;
        $toMany = (string) $method->getReturnType() === 'array';
        $identifier = ['type' => $type, 'id' => '1'];
        $relationships[Str::snake($method->getName())] = ['data' => $toMany ? [$identifier] : $identifier];
        $included[] = $identifier;
    }

    $document = Document::fromArray(['data' => ['type' => $class::TYPE, 'id' => '1', 'relationships' => $relationships], 'included' => $included]);
    $model = (new ModelRegistry())->hydrate($document->resource(), $document->index());

    expect($model)->toBeInstanceOf($class);

    foreach ($accessors as $method) {
        $result = $method->invoke($model);
        expect(is_array($result) ? $result[0] : $result)->toBeInstanceOf(accessorTarget($method));
    }
})->with(fn(): array => generatedModels());
