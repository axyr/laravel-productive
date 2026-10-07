<?php

declare(strict_types=1);

use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\JsonApi\Document;
use Illuminate\Support\Str;

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
        $returnType = ltrim((string) $method->getReturnType(), '?');
        $type = is_subclass_of($returnType, Model::class) ? $returnType::TYPE : 'related_' . $index;
        $toMany = $returnType === 'array';
        $identifier = ['type' => $type, 'id' => '1'];
        $relationships[Str::snake($method->getName())] = ['data' => $toMany ? [$identifier] : $identifier];
        $included[] = $identifier;
    }

    $document = Document::fromArray(['data' => ['type' => $class::TYPE, 'id' => '1', 'relationships' => $relationships], 'included' => $included]);
    $model = (new ModelRegistry())->hydrate($document->resource(), $document->index());

    expect($accessors)->not->toBeEmpty();

    foreach ($accessors as $method) {
        $result = $method->invoke($model);
        $returnType = ltrim((string) $method->getReturnType(), '?');

        expect(is_array($result) ? $result[0] : $result)->toBeInstanceOf($returnType === 'array' ? Model::class : $returnType);
    }
})->with([Task::class, TimeEntry::class, TimeReport::class]);
