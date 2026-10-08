<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\JsonApi\ResourceIdentifier;

it('parses string and integer ids', function () {
    expect(ResourceIdentifier::fromArray(['type' => 'tasks', 'id' => '1'])->toArray())->toBe(['type' => 'tasks', 'id' => '1'])
        ->and(ResourceIdentifier::fromArray(['type' => 'tasks', 'id' => 0])->id)->toBe('0')
        ->and(ResourceIdentifier::fromArray(['type' => 'people', 'id' => 12])->key())->toBe('people:12');
});

it('rejects a missing or invalid type', function (array $data) {
    ResourceIdentifier::fromArray($data);
})->with([
    [['id' => '1']],
    [['type' => '', 'id' => '1']],
    [['type' => 5, 'id' => '1']],
])->throws(InvalidResponseException::class, 'non-empty string "type"');

it('rejects a missing or invalid id', function (array $data) {
    ResourceIdentifier::fromArray($data);
})->with([
    [['type' => 'tasks']],
    [['type' => 'tasks', 'id' => '']],
    [['type' => 'tasks', 'id' => 1.5]],
    [['type' => 'tasks', 'id' => null]],
])->throws(InvalidResponseException::class, 'non-empty "id"');
