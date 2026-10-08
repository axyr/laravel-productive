<?php

declare(strict_types=1);

use Axyr\Productive\Generator\ClassifiedPath;
use Axyr\Productive\Generator\ModelSource;
use Axyr\Productive\Generator\Spec;

function dataResponse(string $marker, ?string $type = 'tasks'): array
{
    $schema = ['title' => $marker, 'properties' => ['data' => ['type' => 'object']]];

    if ($type !== null) {
        $schema['example'] = ['data' => ['type' => $type, 'id' => '1']];
    }

    return ['content' => ['application/vnd.api+json' => ['schema' => $schema]]];
}

function entry(string $path, bool $member, string $method, array $responses, array $actions = []): array
{
    return [new ClassifiedPath($path, 'tasks', $member, $member ? ['id'] : [], $actions), $method, ['responses' => $responses]];
}

it('prefers the show response, then the index response, then any other', function () {
    $spec = new Spec([]);
    $copy = entry('tasks/copy', false, 'POST', ['201' => dataResponse('copy')], ['copy']);
    $index = entry('tasks', false, 'GET', ['200' => dataResponse('index')]);
    $show = entry('tasks/{id}', true, 'GET', ['200' => dataResponse('show')]);

    expect(ModelSource::pick($spec, [$copy, $index, $show])->schema['title'])->toBe('show')
        ->and(ModelSource::pick($spec, [$copy, $index])->schema['title'])->toBe('index')
        ->and(ModelSource::pick($spec, [$copy])->schema['title'])->toBe('copy');
});

it('skips responses without data', function () {
    $source = ModelSource::pick(new Spec([]), [entry('tasks/{id}', true, 'GET', ['204' => [], '422' => dataResponse('error'), '200' => dataResponse('show')])]);

    expect($source->schema['title'])->toBe('show')
        ->and($source->type)->toBe('tasks')
        ->and($source->example)->toBe(['type' => 'tasks', 'id' => '1']);
});

it('falls back to the last path segment for the type', function () {
    $withoutExample = ModelSource::pick(new Spec([]), [[new ClassifiedPath('reports/time_reports', 'reports/time_reports', false, [], []), 'GET', ['responses' => ['200' => dataResponse('index', null)]]]]);
    $withoutData = ModelSource::pick(new Spec([]), [[new ClassifiedPath('reports/time_reports', 'reports/time_reports', false, [], []), 'DELETE', ['responses' => ['204' => []]]]]);

    expect($withoutExample->type)->toBe('time_reports')
        ->and($withoutExample->schema)->not->toBeNull()
        ->and($withoutData->type)->toBe('time_reports')
        ->and($withoutData->schema)->toBeNull()
        ->and($withoutData->example)->toBe([]);
});
