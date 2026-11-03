<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\ConfigMaps;

it('reads an array, or nothing for anything else', function () {
    expect(ConfigMaps::config(['a' => 1]))->toBe(['a' => 1])
        ->and(ConfigMaps::config('a'))->toBe([])
        ->and(ConfigMaps::config(null))->toBe([]);
});

it('keeps the strings of a map', function () {
    expect(ConfigMaps::strings(['a' => 'x', 'b' => 2, 'c' => null, 3 => 'y']))->toBe(['a' => 'x', 3 => 'y'])
        ->and(ConfigMaps::strings('x'))->toBe([]);
});

it('keeps the strings of a list, as a list', function () {
    expect(ConfigMaps::list(['x' => 'tasks', 5, 2 => 'deals']))->toBe(['tasks', 'deals']);
});

it('keeps the strings of each list, as a list', function () {
    expect(ConfigMaps::lists(['CreateTaskData' => ['title', 5, 'x' => 'project_id'], 'CreateNoteData' => 'body']))
        ->toBe(['CreateTaskData' => ['title', 'project_id'], 'CreateNoteData' => []]);
});

it('keeps the recipes that are arrays', function () {
    expect(ConfigMaps::recipes(['tasks' => ['create' => []], 'odd' => 'x']))->toBe(['tasks' => ['create' => []]])
        ->and(ConfigMaps::recipes(null))->toBe([]);
});

it('reads the findings, filling what an entry leaves out', function () {
    $config = ['written' => '2026-11-02', 'findings' => [
        'Routes' => ['first' => ['endpoint' => '`GET /a`', 'spec' => 's', 'api' => 'a', 'reproduce' => 'r', 'extra' => 'x'], ['endpoint' => '`GET /b`', 'spec' => 5], ['api' => 'a']],
        'Odd' => 'x',
    ]];

    expect(ConfigMaps::findings($config))->toBe(['written' => '2026-11-02', 'findings' => [
        'Routes' => [
            ['endpoint' => '`GET /a`', 'spec' => 's', 'api' => 'a', 'reproduce' => 'r'],
            ['endpoint' => '`GET /b`', 'spec' => '', 'api' => '', 'reproduce' => ''],
            ['endpoint' => '', 'spec' => '', 'api' => 'a', 'reproduce' => ''],
        ],
        'Odd' => [],
    ]])
        ->and(ConfigMaps::findings(['written' => 5]))->toBe(['written' => '', 'findings' => []]);
});
