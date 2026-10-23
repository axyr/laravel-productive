<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\Json;

it('lists the resources of a document with their types', function () {
    $task = ['type' => 'tasks', 'id' => '1'];
    $project = ['type' => 'projects', 'id' => 2];

    expect(Json::resources(['data' => ['junk', $task, ['type' => 'tasks']], 'included' => [['id' => '3'], $project]]))->toBe([['tasks', $task], ['projects', $project]])
        ->and(Json::resources(['data' => $task, 'included' => ['type' => 'x']]))->toBe([['tasks', $task]])
        ->and(Json::resources('text'))->toBe([]);
});

it('reads maps and lists defensively', function () {
    expect(Json::map(['a' => 1, 7 => 2]))->toBe(['a' => 1, 7 => 2])
        ->and(Json::map([1, 2]))->toBe([])
        ->and(Json::map('text'))->toBe([])
        ->and(Json::list([1, 2]))->toBe([1, 2])
        ->and(Json::list(['a' => 1]))->toBe([])
        ->and(Json::list(null))->toBe([]);
});

it('decodes a body to JSON, text, or a note for binary', function () {
    expect(Json::decode('{"data": []}'))->toBe(['data' => []])
        ->and(Json::decode('Bad gateway'))->toBe('Bad gateway')
        ->and(Json::decode("%PDF\xff"))->toBe('[5 bytes of binary]');
});
