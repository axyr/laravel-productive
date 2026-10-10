<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\FixtureSelector;

function selectorTask(string $id, array $attributes, array $relationships = []): array
{
    return ['type' => 'tasks', 'id' => $id, 'attributes' => $attributes, 'relationships' => $relationships];
}

it('keeps the fewest resources that show every kind of value and every relationship with data', function () {
    $first = selectorTask('1', ['title' => 'a', 'due_date' => null], ['project' => ['meta' => []]]);
    $same = selectorTask('2', ['title' => 'b', 'due_date' => null]);
    $dated = selectorTask('3', ['title' => 'c', 'due_date' => '2026-10-09']);
    $related = selectorTask('4', ['title' => 'd'], ['project' => ['data' => ['type' => 'projects', 'id' => '1']]]);
    $repeat = selectorTask('5', ['title' => 'e', 'due_date' => '2026-10-10'], ['project' => ['data' => ['type' => 'projects', 'id' => '2']]]);

    $selected = FixtureSelector::select([
        'tasks.index' => ['status' => 200, 'body' => ['data' => [$first, $same, $dated], 'included' => [['type' => 'projects', 'id' => '1', 'attributes' => ['name' => 'p']]]]],
        'tasks.show' => ['status' => 200, 'body' => ['data' => $related]],
        'tasks.index.include' => ['status' => 200, 'body' => ['data' => [$repeat]]],
    ]);

    expect($selected)->toBe([
        'projects' => [['type' => 'projects', 'id' => '1', 'attributes' => ['name' => 'p']]],
        // Recordings are read in name order, so tasks.index.include shows the relationship before tasks.show.
        'tasks' => [$first, $dated, $repeat],
    ]);
});

it('keeps one resource of a type even when it shows nothing', function () {
    $selected = FixtureSelector::select(['teams.index' => ['status' => 200, 'body' => ['data' => [['type' => 'teams', 'id' => '1'], ['type' => 'teams', 'id' => '2']]]]]);

    expect($selected)->toBe(['teams' => [['type' => 'teams', 'id' => '1']]]);
});

it('ignores failed calls and malformed resources', function () {
    $selected = FixtureSelector::select([
        'a' => ['status' => 403, 'body' => ['data' => [selectorTask('1', [])]]],
        'b' => ['status' => 200, 'body' => 'text'],
        'c' => ['status' => 200, 'body' => ['data' => [['type' => 'tasks'], ['id' => '1'], 'junk', ['type' => 5, 'id' => '1']], 'included' => 'junk']],
        'd' => ['body' => ['data' => [selectorTask('1', [])]]],
        'e' => ['status' => 200, 'body' => ['data' => null]],
        'f' => ['status' => 200, 'body' => ['data' => [['type' => 'tasks', 'id' => '9', 'attributes' => 'junk', 'relationships' => ['x' => 'junk']]]]],
    ]);

    expect($selected)->toBe(['tasks' => [['type' => 'tasks', 'id' => '9', 'attributes' => 'junk', 'relationships' => ['x' => 'junk']]]]);
});

it('cuts lists and maps inside attribute values to a few entries', function () {
    $long = range(1, 8);
    $map = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6];

    $selected = FixtureSelector::select(['x' => ['status' => 200, 'body' => ['data' => [selectorTask('1', ['ids' => $long, 'rates' => $map, 'nested' => ['deep' => $long]], ['many' => ['data' => array_map(fn(int $id): array => ['type' => 'x', 'id' => (string) $id], $long)]])]]]]);

    expect($selected['tasks'][0]['attributes'])->toBe(['ids' => [1, 2, 3, 4, 5], 'rates' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], 'nested' => ['deep' => [1, 2, 3, 4, 5]]])
        ->and($selected['tasks'][0]['relationships']['many']['data'])->toHaveCount(8);
});

it('remembers everything already shown, by attribute name and kind', function () {
    $resources = [
        selectorTask('1', ['a' => 1]),
        selectorTask('2', ['b' => 1]),
        selectorTask('3', ['a' => 2]),
        selectorTask('4', ['a' => 'text']),
        selectorTask('5', ['b' => 2], ['a' => ['data' => null]]),
    ];

    expect(array_column(FixtureSelector::select(['x' => ['status' => 200, 'body' => ['data' => $resources]]])['tasks'], 'id'))->toBe(['1', '2', '4']);
});

it('keeps the keys of maps it cuts', function () {
    $selected = FixtureSelector::select(['x' => ['status' => 200, 'body' => ['data' => [selectorTask('1', ['custom_fields' => [71 => 'a', 72 => 'b']])]]]]);

    expect($selected['tasks'][0]['attributes']['custom_fields'])->toBe([71 => 'a', 72 => 'b']);
});
