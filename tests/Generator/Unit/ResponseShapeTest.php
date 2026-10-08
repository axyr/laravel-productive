<?php

declare(strict_types=1);

use Axyr\Productive\Generator\ResponseShape;
use Axyr\Productive\Generator\Spec;

function shapeSpec(): Spec
{
    return new Spec(['components' => ['responses' => [
        'single' => ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => ['type' => 'object']]]]]],
        'collection' => ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => ['type' => 'array']]]]]],
    ]]]);
}

it('reads the shape of a single resource response', function () {
    $shape = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['200' => ['$ref' => '#/components/responses/single'], '422' => []]]);

    expect($shape->resource)->toBeTrue()
        ->and($shape->collection)->toBeFalse()
        ->and($shape->noContent)->toBeFalse()
        ->and($shape->emptyOk)->toBeFalse()
        ->and($shape->schema)->toBe(['properties' => ['data' => ['type' => 'object']]]);
});

it('reads the shape of a collection response', function () {
    $shape = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['200' => ['$ref' => '#/components/responses/collection']]]);

    expect($shape->collection)->toBeTrue()
        ->and($shape->resource)->toBeFalse();
});

it('combines a data response with a response without content', function () {
    $shape = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['204' => ['description' => 'No Content'], '200' => ['$ref' => '#/components/responses/single']]]);

    expect($shape->resource)->toBeTrue()
        ->and($shape->noContent)->toBeTrue()
        ->and($shape->schema)->not->toBeNull();
});

it('tells an empty 200 apart from a 204', function () {
    $empty = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['200' => ['description' => 'OK']]]);
    $noContent = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['204' => ['content' => ['any' => ['schema' => []]]]]]);

    expect($empty->emptyOk)->toBeTrue()
        ->and($empty->noContent)->toBeFalse()
        ->and($empty->schema)->toBeNull()
        ->and($noContent->noContent)->toBeTrue()
        ->and($noContent->emptyOk)->toBeFalse();
});

it('is empty without success responses', function () {
    $shape = ResponseShape::fromOperation(shapeSpec(), ['responses' => ['404' => []]]);

    expect([$shape->collection, $shape->resource, $shape->noContent, $shape->emptyOk, $shape->schema])->toBe([false, false, false, false, null]);
});

it('marks plain JSON responses', function () {
    $shape = ResponseShape::fromOperation(shapeSpec(), ['responses' => [
        '200' => ['$ref' => '#/components/responses/single'],
        '201' => ['content' => ['application/json' => ['schema' => ['properties' => ['data' => ['type' => 'object']]]]]],
    ]]);

    expect($shape->plainJson)->toBeTrue()
        ->and($shape->resource)->toBeTrue()
        ->and(ResponseShape::fromOperation(shapeSpec(), ['responses' => ['200' => ['$ref' => '#/components/responses/single']]])->plainJson)->toBeFalse();
});
