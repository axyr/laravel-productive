<?php

declare(strict_types=1);

use Axyr\Productive\Generator\PathClassifier;

function classifier(): PathClassifier
{
    return new PathClassifier([
        'tasks', 'tasks/{id}', 'tasks/{id}/reposition', 'tasks/copy',
        'reports/time_reports', 'exchange_rates', 'sessions', 'sessions/{id}', 'sessions/machine',
        'public/artifacts/{uuid}', 'public/artifacts/{uuid}/attachments/{id}/auth',
    ]);
}

it('finds the resource roots', function () {
    expect(classifier()->roots())->toBe(['exchange_rates', 'public/artifacts', 'reports/time_reports', 'sessions', 'tasks']);
});

it('classifies collections, members and actions', function (string $path, string $resource, bool $member, array $parameters, ?string $action) {
    $classified = classifier()->classify($path);

    expect($classified->path)->toBe($path)
        ->and($classified->resource)->toBe($resource)
        ->and($classified->member)->toBe($member)
        ->and($classified->parameters)->toBe($parameters)
        ->and($classified->action())->toBe($action);
})->with([
    ['tasks', 'tasks', false, [], null],
    ['tasks/{id}', 'tasks', true, ['id'], null],
    ['tasks/{id}/reposition', 'tasks', true, ['id'], 'reposition'],
    ['tasks/copy', 'tasks', false, [], 'copy'],
    ['reports/time_reports', 'reports/time_reports', false, [], null],
    ['exchange_rates', 'exchange_rates', false, [], null],
    ['sessions/machine', 'sessions', false, [], 'machine'],
    ['public/artifacts/{uuid}/attachments/{id}/auth', 'public/artifacts', true, ['uuid', 'id'], 'attachments_auth'],
]);

it('classifies parents before children', function () {
    $classifier = new PathClassifier(['x/y/z', 'x/y', 'x']);

    expect($classifier->roots())->toBe(['x', 'x/y/z'])
        ->and($classifier->classify('x/y')->resource)->toBe('x')
        ->and($classifier->classify('x/y')->action())->toBe('y')
        ->and($classifier->classify('x')->actionName())->toBe('');
});
