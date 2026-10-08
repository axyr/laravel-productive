<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\JsonApi\Relationship;
use Axyr\Productive\JsonApi\ResourceIdentifier;
use Axyr\Productive\JsonApi\ResourceObject;

it('parses a full resource object', function () {
    $resource = ResourceObject::fromArray([
        'type' => 'tasks',
        'id' => 120501,
        'attributes' => ['title' => 'Write docs', 'closed' => false],
        'relationships' => ['assignee' => ['data' => ['type' => 'people', 'id' => '12']]],
        'links' => ['self' => 'https://example.test'],
        'meta' => ['permissions' => ['can_edit' => true]],
    ]);

    expect($resource->type)->toBe('tasks')
        ->and($resource->id)->toBe('120501')
        ->and($resource->identifier())->toEqual(new ResourceIdentifier('tasks', '120501'))
        ->and($resource->attribute('title'))->toBe('Write docs')
        ->and($resource->attribute('closed'))->toBeFalse()
        ->and($resource->attribute('missing'))->toBeNull()
        ->and($resource->relationship('assignee'))->toBeInstanceOf(Relationship::class)
        ->and($resource->relationship('project'))->toBeNull()
        ->and($resource->links)->toBe(['self' => 'https://example.test'])
        ->and($resource->meta)->toBe(['permissions' => ['can_edit' => true]]);
});

it('defaults optional members to empty', function () {
    $resource = ResourceObject::fromArray(['type' => 'tasks', 'id' => '1', 'attributes' => []]);

    expect($resource->attributes)->toBe([])
        ->and($resource->relationships)->toBe([])
        ->and($resource->links)->toBe([])
        ->and($resource->meta)->toBe([]);
});

it('rejects members that are not objects', function (string $member, mixed $value) {
    expect(fn() => ResourceObject::fromArray(['type' => 'tasks', 'id' => '1', $member => $value]))
        ->toThrow(InvalidResponseException::class, sprintf('JSON:API member "%s" must be an object.', $member));
})->with([
    ['attributes', 'x'],
    ['attributes', ['a', 'b']],
    ['meta', 5],
    ['relationships', ['x']],
]);

it('rejects a relationship that is not an object', function () {
    ResourceObject::fromArray(['type' => 'tasks', 'id' => '1', 'relationships' => ['assignee' => 'people']]);
})->throws(InvalidResponseException::class, 'A JSON:API relationship must be an object.');
