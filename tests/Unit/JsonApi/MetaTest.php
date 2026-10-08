<?php

declare(strict_types=1);

use Axyr\Productive\JsonApi\Meta;

it('reads non-negative integers from numbers and numeric strings', function (mixed $value, ?int $expected) {
    expect(Meta::int(['total_count' => $value], 'total_count'))->toBe($expected);
})->with([
    [120, 120],
    [0, 0],
    ['120', 120],
    ['0', 0],
    ['12.5', null],
    ['-3', null],
    ['', null],
    [12.0, null],
    [null, null],
]);

it('returns null for a missing key', function () {
    expect(Meta::int([], 'total_count'))->toBeNull();
});
