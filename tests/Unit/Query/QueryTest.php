<?php

declare(strict_types=1);

use Axyr\Productive\Enums\TaskSort;
use Axyr\Productive\Enums\TimeReportGroup;
use Axyr\Productive\Exceptions\InvalidQueryException;
use Axyr\Productive\Query\FilterGroup;
use Axyr\Productive\Query\Operator;
use Axyr\Productive\Query\Query;

it('serializes an empty query to an empty string', function () {
    expect((new Query())->toQueryString())->toBe('');
});

it('serializes the simple filter form from the filtering guide', function () {
    expect((new Query())->where('person_id', 24)->toQueryString())->toBe('filter[person_id]=24')
        ->and((new Query())->where('person_id', 'not_eq', 24)->toQueryString())->toBe('filter[person_id][not_eq]=24');
});

it('serializes the logical example from the filtering guide', function () {
    $query = (new Query())
        ->whereAny(fn(FilterGroup $any) => $any
            ->where('name', Operator::Eq, 'Productive')
            ->whereAll(fn(FilterGroup $all) => $all
                ->where('date', '>', '2024-01-01')
                ->where('date', '=', '2023-01-01')));

    expect(urldecode($query->toQueryString()))->toBe(
        'filter[$op]=and&filter[0][$op]=or&filter[0][0][name][eq]=Productive'
        . '&filter[0][1][$op]=and&filter[0][1][0][date][gt]=2024-01-01&filter[0][1][1][date][eq]=2023-01-01',
    );
});

it('uses the logical form when a field repeats', function () {
    $query = (new Query())->where('date', '>=', '2026-01-01')->where('date', '<', '2026-02-01');

    expect($query->toQueryString())->toBe('filter[$op]=and&filter[0][date][gt_eq]=2026-01-01&filter[1][date][lt]=2026-02-01');
});

it('mixes plain conditions with a nested group in the logical form', function () {
    $query = (new Query())->where('project_id', 1)->whereAny(fn(FilterGroup $any) => $any->where('a', 1)->where('b', 2));

    expect($query->toQueryString())->toBe('filter[$op]=and&filter[0][project_id][eq]=1&filter[1][$op]=or&filter[1][0][a][eq]=1&filter[1][1][b][eq]=2');
});

it('encodes values as the filtering guide requires', function () {
    expect((new Query())->where('created_at', '>', '2024-09-12T10:15:30+02:00')->toQueryString())
        ->toBe('filter[created_at][gt]=2024-09-12T10%3A15%3A30%2B02%3A00')
        ->and((new Query())->where('title', 'a b&c=d')->toQueryString())->toBe('filter[title]=a%20b%26c%3Dd')
        ->and((new Query())->where('id', [1, 2, 3])->toQueryString())->toBe('filter[id]=1,2,3');
});

it('serializes nested filter fields', function () {
    expect((new Query())->where('custom_fields.123', 'x')->toQueryString())->toBe('filter[custom_fields][123]=x');
});

it('serializes sort, include, group and pagination in a fixed order', function () {
    $query = (new Query())
        ->perPage(50)
        ->page(2)
        ->group(TimeReportGroup::Person, 'project')
        ->include('assignee', 'project.company', 'assignee')
        ->sort(TaskSort::DueDateDesc, 'title')
        ->where('project_id', 1);

    expect($query->toQueryString())->toBe('filter[project_id]=1&sort=-due_date,title&include=assignee,project.company&group=person,project&page[number]=2&page[size]=50');
});

it('serializes the cursor pagination example from the pagination guide', function () {
    expect((new Query())->after('')->perPage(200)->toQueryString())->toBe('page[size]=200&page[after]=')
        ->and((new Query())->after('eyJ2IjoxLCJzb3J0Ijo')->toQueryString())->toBe('page[after]=eyJ2IjoxLCJzb3J0Ijo');
});

it('sorts with orderBy', function () {
    expect((new Query())->orderBy('title')->orderBy('due_date', 'DESC')->toQueryString())->toBe('sort=title,-due_date');
});

it('rejects an unknown sort direction', function () {
    (new Query())->orderBy('title', 'sideways');
})->throws(InvalidQueryException::class, 'Sort direction must be "asc" or "desc", "sideways" given.');

it('rejects invalid sort, include and group names', function (Closure $build, string $message) {
    expect(fn() => $build(new Query()))->toThrow(InvalidQueryException::class, $message);
})->with([
    [fn(Query $query) => $query->sort('due date'), 'Invalid sort value "due date".'],
    [fn(Query $query) => $query->sort('--title'), 'Invalid sort value'],
    [fn(Query $query) => $query->include('project,company'), 'Invalid include value'],
    [fn(Query $query) => $query->include('-project'), 'Invalid include value'],
    [fn(Query $query) => $query->group(''), 'Invalid group value'],
]);

it('accepts negative-free include names but descending sort names', function () {
    expect((new Query())->sort('-title')->toQueryString())->toBe('sort=-title');
});

it('validates page numbers and sizes', function () {
    expect(fn() => (new Query())->page(0))->toThrow(InvalidQueryException::class, 'The page number must be 1 or higher.')
        ->and(fn() => (new Query())->perPage(0))->toThrow(InvalidQueryException::class, 'The page size must be between 1 and 200.')
        ->and(fn() => (new Query())->perPage(201))->toThrow(InvalidQueryException::class, 'between 1 and 200')
        ->and((new Query())->page(1)->perPage(1)->perPage(200)->toQueryString())->toBe('page[number]=1&page[size]=200');
});

it('refuses cursor and page numbers together', function () {
    (new Query())->after('')->page(2)->toQueryString();
})->throws(InvalidQueryException::class, 'cannot use cursor pagination (after) and page numbers (page) at the same time');

it('reports its pagination state', function () {
    $query = new Query();

    expect($query->hasSort())->toBeFalse()
        ->and($query->usesCursor())->toBeFalse()
        ->and($query->pageSize())->toBeNull();

    $query->sort('title')->after('')->perPage(10);

    expect($query->hasSort())->toBeTrue()
        ->and($query->usesCursor())->toBeTrue()
        ->and($query->pageSize())->toBe(10);
});

it('clones filters independently', function () {
    $original = (new Query())->where('a', 1);
    $copy = (clone $original)->where('b', 2);

    expect($original->toQueryString())->toBe('filter[a]=1')
        ->and($copy->toQueryString())->toBe('filter[a]=1&filter[b]=2');
});

it('supports where groups directly on the query', function () {
    $query = (new Query())->whereAll(fn(FilterGroup $all) => $all->where('a', 1));

    expect($query->toQueryString())->toBe('filter[$op]=and&filter[0][$op]=and&filter[0][0][a][eq]=1');
});

enum QueryTestIntGroup: int
{
    case Five = 5;
}

it('accepts integer-backed enums for names', function () {
    expect((new Query())->group(QueryTestIntGroup::Five)->sort(QueryTestIntGroup::Five)->toQueryString())->toBe('sort=5&group=5');
});
