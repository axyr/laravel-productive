<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\JsonApi\Document;
use Axyr\Productive\JsonApi\ResourceIdentifier;
use Axyr\Productive\JsonApi\ResourceObject;

it('parses a single resource document', function () {
    $document = Document::fromArray([
        'data' => ['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'A']],
        'included' => [['type' => 'people', 'id' => '12', 'attributes' => []]],
        'meta' => ['x' => 1],
    ]);

    expect($document->isCollection())->toBeFalse()
        ->and($document->resource()->id)->toBe('1')
        ->and($document->included)->toHaveCount(1)
        ->and($document->meta)->toBe(['x' => 1])
        ->and($document->links)->toBe([]);
});

it('parses a collection document', function () {
    $document = Document::fromArray([
        'data' => [['type' => 'tasks', 'id' => '1'], ['type' => 'tasks', 'id' => '2']],
        'meta' => ['total_count' => '42'],
        'links' => ['next' => 'https://example.test/next'],
    ]);

    expect($document->isCollection())->toBeTrue()
        ->and($document->resources())->toHaveCount(2)
        ->and($document->metaInt('total_count'))->toBe(42)
        ->and($document->metaInt('missing'))->toBeNull()
        ->and($document->link('next'))->toBe('https://example.test/next');
});

it('treats an empty data array as an empty collection', function () {
    $document = Document::fromArray(['data' => []]);

    expect($document->isCollection())->toBeTrue()
        ->and($document->resources())->toBe([]);
});

it('allows null data', function () {
    $document = Document::fromArray(['data' => null]);

    expect($document->data)->toBeNull()
        ->and($document->index()->count())->toBe(0)
        ->and(fn() => $document->resource())->toThrow(InvalidResponseException::class, 'Expected a single resource')
        ->and(fn() => $document->resources())->toThrow(InvalidResponseException::class, 'Expected a collection');
});

it('reads links in string and object form', function () {
    $document = Document::fromArray(['data' => [], 'links' => ['next' => ['href' => 'https://example.test/n'], 'prev' => '', 'first' => null]]);

    expect($document->link('next'))->toBe('https://example.test/n')
        ->and($document->link('prev'))->toBeNull()
        ->and($document->link('first'))->toBeNull()
        ->and($document->link('last'))->toBeNull();
});

it('indexes primary data and included resources', function () {
    $document = Document::fromArray([
        'data' => [['type' => 'tasks', 'id' => '1']],
        'included' => [['type' => 'people', 'id' => '12'], ['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'duplicate']]],
    ]);
    $index = $document->index();

    expect($index->count())->toBe(2)
        ->and($index->find(new ResourceIdentifier('people', '12')))->toBeInstanceOf(ResourceObject::class)
        ->and($index->find(new ResourceIdentifier('tasks', '1'))?->attributes)->toBe([])
        ->and($index->find(new ResourceIdentifier('people', '99')))->toBeNull();
});

it('indexes a single resource as primary data', function () {
    $document = Document::fromArray(['data' => ['type' => 'tasks', 'id' => '1']]);

    expect($document->index()->find(new ResourceIdentifier('tasks', '1')))->toBe($document->data);
});

it('rejects documents that are not JSON:API', function (array $document, string $message) {
    expect(fn() => Document::fromArray($document))->toThrow(InvalidResponseException::class, $message);
})->with([
    'no data' => [['errors' => []], 'the "data" member is missing'],
    'scalar data' => [['data' => 'x'], '"data" must be null, an object or an array'],
    'included object' => [['data' => null, 'included' => ['type' => 'x']], 'resource list must be an array'],
    'included scalar' => [['data' => null, 'included' => ['x']], 'must contain objects'],
    'list meta' => [['data' => null, 'meta' => [1]], '"meta" and "links" must be objects'],
    'scalar links' => [['data' => null, 'links' => 'x'], '"meta" and "links" must be objects'],
    'collection of scalars' => [['data' => [1]], 'must contain objects'],
]);
