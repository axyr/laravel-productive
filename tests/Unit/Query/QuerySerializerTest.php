<?php

declare(strict_types=1);

use Axyr\Productive\Query\FilterGroup;
use Axyr\Productive\Query\Logical;
use Axyr\Productive\Query\Operator;
use Axyr\Productive\Query\QuerySerializer;

it('serializes a root OR group in the logical form', function () {
    $filters = (new FilterGroup(Logical::Or))->where('a', 1)->where('b', 2);

    expect(QuerySerializer::serialize($filters))->toBe('filter[$op]=or&filter[0][a][eq]=1&filter[1][b][eq]=2');
});

it('serializes an empty query', function () {
    expect(QuerySerializer::serialize(new FilterGroup()))->toBe('');
});

it('serializes only the parts that are set', function () {
    expect(QuerySerializer::serialize(new FilterGroup(), includes: ['project'], page: ['after' => '']))->toBe('include=project&page[after]=');
});

it('continues after a nested group', function () {
    $filters = (new FilterGroup())->whereAny(fn(FilterGroup $any) => $any->where('a', 1))->where('b', 2);

    expect(QuerySerializer::serialize($filters))->toBe('filter[$op]=and&filter[0][$op]=or&filter[0][0][a][eq]=1&filter[1][b][eq]=2');
});

it('uses the logical form when a field/operator pair repeats among many conditions', function () {
    $filters = (new FilterGroup())
        ->where('a', 'eq', 1)
        ->where('a', 'gt_eq', 2)
        ->where('date', 'lt_eq', 3)
        ->where('b', 'not_eq', 4)
        ->where('b', 'eq', 5)
        ->where('a', 'gt_eq', 6)
        ->where('a', 'contains', 7);

    expect(QuerySerializer::serialize($filters))->toBe(
        'filter[$op]=and&filter[0][a][eq]=1&filter[1][a][gt_eq]=2&filter[2][date][lt_eq]=3&filter[3][b][not_eq]=4'
        . '&filter[4][b][eq]=5&filter[5][a][gt_eq]=6&filter[6][a][contains]=7',
    );
});

it('uses the logical form for an order that array_unique misses on PHP 8.4', function () {
    $filters = new FilterGroup();

    foreach ([['date', 'gt_eq'], ['date', 'lt'], ['a', 'not_eq'], ['b', 'contains'], ['b', 'not_eq'], ['b', 'lt_eq'], ['b', 'not_eq'], ['date', 'eq']] as [$field, $operator]) {
        $filters->where($field, $operator, 'x');
    }

    expect(QuerySerializer::serialize($filters))->toStartWith('filter[$op]=and&');
});

it('never sends the same filter key twice', function () {
    mt_srand(20261008);
    $operators = Operator::cases();

    for ($i = 0; $i < 2000; $i++) {
        $filters = new FilterGroup();
        $keys = [];

        for ($n = 0, $length = mt_rand(1, 8); $n < $length; $n++) {
            [$field, $operator] = [['a', 'b', 'date'][mt_rand(0, 2)], $operators[mt_rand(0, 7)]];
            $filters->where($field, $operator, 'x');
            $keys[] = $field . '|' . $operator->value;
        }

        $query = QuerySerializer::serialize($filters);
        $sentKeys = array_map(fn(string $pair): string => explode('=', $pair)[0], explode('&', $query));

        expect(str_starts_with($query, 'filter[$op]='))->toBe(count(array_unique($keys)) !== count($keys))
            ->and(array_unique($sentKeys))->toHaveCount(count($sentKeys));
    }
});
