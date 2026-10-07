<?php

declare(strict_types=1);

use Axyr\Productive\JsonApi\ErrorObject;

it('parses the standard members', function () {
    $error = ErrorObject::fromArray([
        'id' => 'abc',
        'status' => '422',
        'title' => 'Invalid Attribute',
        'detail' => "can't be blank",
        'code' => 'blank',
        'source' => ['pointer' => '/data/attributes/title', 'parameter' => 'filter[title]'],
        'links' => ['about' => 'https://example.test'],
    ]);

    expect($error->status)->toBe(422)
        ->and($error->title)->toBe('Invalid Attribute')
        ->and($error->detail)->toBe("can't be blank")
        ->and($error->code)->toBe('blank')
        ->and($error->pointer)->toBe('/data/attributes/title')
        ->and($error->parameter)->toBe('filter[title]')
        ->and($error->meta)->toBe([])
        ->and($error->attribute())->toBe('title')
        ->and($error->message())->toBe("can't be blank");
});

it('keeps non-standard members and meta together', function () {
    $error = ErrorObject::fromArray([
        'status' => 429,
        'title' => 'Server time limit exceeded',
        'limit' => 1800,
        'period' => 3600,
        'meta' => ['retry' => true],
    ]);

    expect($error->meta)->toBe(['retry' => true, 'limit' => 1800, 'period' => 3600]);
});

it('handles missing and malformed members', function () {
    $error = ErrorObject::fromArray(['status' => 'oops', 'title' => '', 'code' => 7, 'source' => 'x', 'meta' => 'y']);

    expect($error->status)->toBeNull()
        ->and($error->title)->toBeNull()
        ->and($error->code)->toBe('7')
        ->and($error->pointer)->toBeNull()
        ->and($error->meta)->toBe([])
        ->and($error->attribute())->toBeNull()
        ->and($error->message())->toBe('Unknown error');
});

it('falls back to the title for the message', function () {
    expect(ErrorObject::fromArray(['title' => 'Access Denied'])->message())->toBe('Access Denied');
});

it('takes the last pointer segment as attribute', function (string $pointer, ?string $attribute) {
    expect((new ErrorObject(pointer: $pointer))->attribute())->toBe($attribute);
})->with([
    ['/data/attributes/due_date', 'due_date'],
    ['/data/relationships/project/', 'project'],
    ['/', null],
    ['', null],
]);
