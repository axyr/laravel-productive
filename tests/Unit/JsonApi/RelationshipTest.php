<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\JsonApi\Relationship;
use Axyr\Productive\JsonApi\ResourceIdentifier;

it('knows when Productive did not include the data', function () {
    $relationship = Relationship::fromArray(['meta' => ['included' => false]]);

    expect($relationship->hasData)->toBeFalse()
        ->and($relationship->data)->toBeNull()
        ->and($relationship->meta)->toBe(['included' => false])
        ->and($relationship->identifiers())->toBe([])
        ->and($relationship->isToMany())->toBeFalse();
});

it('parses an empty to-one', function () {
    $relationship = Relationship::fromArray(['data' => null]);

    expect($relationship->hasData)->toBeTrue()
        ->and($relationship->data)->toBeNull()
        ->and($relationship->identifiers())->toBe([]);
});

it('parses a to-one', function () {
    $relationship = Relationship::fromArray(['data' => ['type' => 'people', 'id' => '12'], 'links' => ['related' => 'x']]);

    expect($relationship->data)->toEqual(new ResourceIdentifier('people', '12'))
        ->and($relationship->isToMany())->toBeFalse()
        ->and($relationship->identifiers())->toEqual([new ResourceIdentifier('people', '12')])
        ->and($relationship->links)->toBe(['related' => 'x']);
});

it('parses a to-many', function () {
    $relationship = Relationship::fromArray(['data' => [['type' => 'attachments', 'id' => '1'], ['type' => 'attachments', 'id' => '2']]]);

    expect($relationship->isToMany())->toBeTrue()
        ->and(array_map(fn(ResourceIdentifier $identifier): string => $identifier->id, $relationship->identifiers()))->toBe(['1', '2']);
});

it('parses an empty to-many', function () {
    $relationship = Relationship::fromArray(['data' => []]);

    expect($relationship->isToMany())->toBeTrue()
        ->and($relationship->identifiers())->toBe([]);
});

it('ignores malformed meta and links', function () {
    $relationship = Relationship::fromArray(['data' => null, 'meta' => 'x', 'links' => 1]);

    expect($relationship->meta)->toBe([])
        ->and($relationship->links)->toBe([]);
});

it('rejects invalid data', function (mixed $data, string $message) {
    expect(fn() => Relationship::fromArray(['data' => $data]))->toThrow(InvalidResponseException::class, $message);
})->with([
    ['people', 'must be null, an object or an array'],
    [['people'], 'must contain objects'],
]);
