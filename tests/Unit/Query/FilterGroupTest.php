<?php

declare(strict_types=1);

use Axyr\Productive\Query\Condition;
use Axyr\Productive\Query\FilterGroup;
use Axyr\Productive\Query\Logical;
use Axyr\Productive\Query\Operator;

it('adds equality conditions with two arguments', function () {
    $group = (new FilterGroup())->where('project_id', 5);
    $condition = $group->items()[0];

    expect($group->logical)->toBe(Logical::And)
        ->and($condition)->toBeInstanceOf(Condition::class)
        ->and($condition->operator)->toBe(Operator::Eq)
        ->and($condition->explicitOperator)->toBeFalse()
        ->and($condition->value)->toBe('5');
});

it('adds conditions with an explicit operator', function () {
    $items = (new FilterGroup())
        ->where('due_date', '>=', '2026-01-01')
        ->where('title', Operator::Contains, 'x')
        ->where('status', 'eq', null === null ? 'open' : 'x')
        ->items();

    expect($items[0]->operator)->toBe(Operator::GtEq)
        ->and($items[1]->operator)->toBe(Operator::Contains)
        ->and($items[2]->explicitOperator)->toBeTrue();
});

it('treats a three-argument null as a value to validate', function () {
    (new FilterGroup())->where('a', '=', null);
})->throws(Axyr\Productive\Exceptions\InvalidQueryException::class);

it('rejects operators that are not strings or enums', function () {
    (new FilterGroup())->where('a', 5, 1);
})->throws(Axyr\Productive\Exceptions\InvalidQueryException::class, 'Unknown filter operator "int"');

it('nests any and all groups', function () {
    $group = (new FilterGroup())
        ->whereAny(fn(FilterGroup $any) => $any->where('a', 1)->whereAll(fn(FilterGroup $all) => $all->where('b', 2)));

    $any = $group->items()[0];

    expect($any)->toBeInstanceOf(FilterGroup::class)
        ->and($any->logical)->toBe(Logical::Or)
        ->and($any->items()[1]->logical)->toBe(Logical::And);
});

it('skips empty nested groups', function () {
    $group = (new FilterGroup())->whereAny(fn(FilterGroup $any) => null);

    expect($group->isEmpty())->toBeTrue();
});

it('deep clones nested groups', function () {
    $original = (new FilterGroup())->whereAny(fn(FilterGroup $any) => $any->where('a', 1));
    $copy = clone $original;
    $copy->items()[0]->where('b', 2);
    $copy->where('c', 3);

    expect($original->items())->toHaveCount(1)
        ->and($original->items()[0]->items())->toHaveCount(1)
        ->and($copy->items())->toHaveCount(2);
});
