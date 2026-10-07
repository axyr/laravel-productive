<?php

declare(strict_types=1);

use Axyr\Productive\Query\FilterGroup;
use Axyr\Productive\Query\Logical;
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
