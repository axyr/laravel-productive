<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\SeenIds;

it('keeps the first ID of every type, primary or included, and ignores malformed ones', function () {
    $ids = new SeenIds();

    $ids->remember(['data' => [['id' => 7, 'type' => 'tasks'], ['id' => '8', 'type' => 'tasks']], 'included' => [['id' => ['x'], 'type' => 'people'], ['id' => '2', 'type' => 'people']]]);
    $ids->remember(['data' => ['id' => '9', 'type' => 'tasks']]);
    $ids->remember('not a document');

    expect($ids->first('tasks'))->toBe('7')
        ->and($ids->first('people'))->toBe('2')
        ->and($ids->first('projects'))->toBeNull();
});
