<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidQueryException;
use Axyr\Productive\Query\Operator;

it('exposes the operators from the filtering guide', function () {
    expect(array_map(fn(Operator $operator): string => $operator->value, Operator::cases()))
        ->toBe(['eq', 'not_eq', 'contains', 'not_contain', 'gt', 'gt_eq', 'lt', 'lt_eq']);
});

it('parses names, symbols and enum cases', function (Operator|string $input, Operator $expected) {
    expect(Operator::parse($input))->toBe($expected);
})->with([
    [Operator::Contains, Operator::Contains],
    ['gt_eq', Operator::GtEq],
    ['not_contain', Operator::NotContain],
    ['=', Operator::Eq],
    ['==', Operator::Eq],
    ['!=', Operator::NotEq],
    ['<>', Operator::NotEq],
    ['>', Operator::Gt],
    ['>=', Operator::GtEq],
    ['<', Operator::Lt],
    ['<=', Operator::LtEq],
]);

it('rejects unknown operators with the valid list', function () {
    Operator::parse('like');
})->throws(InvalidQueryException::class, 'Unknown filter operator "like". Use one of: eq, not_eq, contains, not_contain, gt, gt_eq, lt, lt_eq.');
