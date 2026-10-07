<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Exceptions\BadRequestException;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\Testing\Factories\TaskFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

/**
 * @param  list<string>  $ids
 */
function taskPage(array $ids, ?string $next = null, array $meta = []): array
{
    return [
        'data' => array_map(fn(string $id): array => TaskFactory::new()->resource(['id' => $id]), $ids),
        'meta' => ['page_size' => 200, 'max_page_size' => 200, ...$meta],
        'links' => $next === null ? ['first' => apiUrl('tasks?page[after]=')] : ['next' => $next],
    ];
}

it('follows cursor links through every page', function () {
    fakeHttp(['*' => Http::sequence()
        ->push(taskPage(['1', '2'], apiUrl('tasks?page[after]=c1&page[size]=200')))
        ->push(taskPage(['3'], apiUrl('tasks?page[after]=c2&page[size]=200')))
        ->push(taskPage(['4']))]);

    $ids = Productive::tasks()->query()->where('project_id', 1)->lazy()->map(fn(Task $task): string => $task->id)->all();

    expect($ids)->toBe(['1', '2', '3', '4']);
    Http::assertSentInOrder([
        fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?filter[project_id]=1&page[size]=200&page[after]='),
        fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?page[after]=c1&page[size]=200'),
        fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?page[after]=c2&page[size]=200'),
    ]);
});

it('keeps a custom page size and stops on an empty page', function () {
    fakeHttp(['*' => Http::sequence()
        ->push(taskPage(['1'], apiUrl('tasks?page[after]=c1')))
        ->push(taskPage([], apiUrl('tasks?page[after]=c2')))]);

    $all = Productive::tasks()->query()->perPage(25)->all();

    expect($all->pluck('id')->all())->toBe(['1']);
    Http::assertSentCount(2);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?page[size]=25&page[after]='));
});

it('stops when the next link is empty', function () {
    fakeHttp(['*' => Http::response([...taskPage(['1']), 'links' => ['next' => '']])]);

    expect(Productive::tasks()->query()->all())->toHaveCount(1);
    Http::assertSentCount(1);
});

it('falls back to page numbers when the sort cannot be paginated by cursor', function () {
    fakeHttp(['*' => Http::sequence()
        ->push(['errors' => [['status' => 400, 'title' => 'Bad Request', 'code' => 'keyset_unsupported_sort']]], 400)
        ->push(taskPage(['1'], meta: ['total_pages' => 2, 'current_page' => 1]))
        ->push(taskPage(['2'], meta: ['total_pages' => 2, 'current_page' => 2]))]);

    $ids = Productive::tasks()->query()->sort('-placement')->all()->pluck('id')->all();

    expect($ids)->toBe(['1', '2']);
    Http::assertSentInOrder([
        fn(HttpRequest $request): bool => str_contains(urldecode($request->url()), 'page[after]='),
        fn(HttpRequest $request): bool => str_ends_with(urldecode($request->url()), 'sort=-placement&page[number]=1&page[size]=200'),
        fn(HttpRequest $request): bool => str_ends_with(urldecode($request->url()), 'sort=-placement&page[number]=2&page[size]=200'),
    ]);
});

it('rethrows other bad requests', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 400, 'title' => 'Unsupported Filter']]], 400)]);

    Productive::tasks()->query()->where('nope', 1)->all();
})->throws(BadRequestException::class, 'Unsupported Filter');

it('stops numbered paging when total_pages is missing', function () {
    fakeHttp(['*' => Http::response(['data' => [], 'meta' => []])]);

    expect(Productive::reports()->timeReports()->query()->all())->toBeEmpty();
    Http::assertSentCount(1);
});

it('stops numbered paging on an empty page even if more pages are announced', function () {
    fakeHttp(['*' => Http::response(['data' => [], 'meta' => ['total_pages' => 5]])]);

    expect(Productive::reports()->timeReports()->query()->all())->toBeEmpty();
    Http::assertSentCount(1);
});

it('returns a single page with meta and links', function () {
    fakeHttp(['*' => Http::response(taskPage(['1', '2'], apiUrl('tasks?page[after]=x'), ['total_count' => 9]))]);

    $page = Productive::tasks()->query()->page(3)->perPage(2)->get();

    expect($page)->toBeInstanceOf(ModelCollection::class)
        ->and($page)->toHaveCount(2)
        ->and($page->totalCount())->toBe(9)
        ->and($page->links())->toBe(['next' => apiUrl('tasks?page[after]=x')]);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?page[number]=3&page[size]=2'));
});

it('returns the first model or null', function () {
    fakeHttp(['*' => Http::sequence()->push(taskPage(['7']))->push(taskPage([]))]);

    expect(Productive::tasks()->query()->where('title', 'x')->first()?->id)->toBe('7')
        ->and(Productive::tasks()->query()->first())->toBeNull();
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?filter[title]=x&page[size]=1'));
});

it('counts matching records from a one-item page', function () {
    fakeHttp(['*' => Http::sequence()->push(taskPage(['1'], meta: ['total_count' => 1234]))->push(taskPage(['1']))]);

    $query = Productive::tasks()->query()->where('project_id', 1);

    expect($query->count())->toBe(1234)
        ->and(Productive::tasks()->query()->count())->toBe(1);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?filter[project_id]=1&page[number]=1&page[size]=1'));
    expect($query->toQueryString())->toBe('filter[project_id]=1');
});

it('paginates with a Laravel length-aware paginator', function () {
    fakeHttp(['*' => Http::response(taskPage(['1', '2'], meta: ['total_count' => 42, 'current_page' => 2]))]);

    $paginator = Productive::tasks()->query()->paginate(perPage: 2, page: 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(42)
        ->and($paginator->perPage())->toBe(2)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->lastPage())->toBe(21)
        ->and($paginator->items())->toHaveCount(2);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('tasks?page[number]=2&page[size]=2'));
});

it('paginates without page meta', function () {
    fakeHttp(['*' => Http::response(['data' => [TaskFactory::new()->resource()]])]);

    $paginator = Productive::tasks()->query()->paginate(perPage: 10, page: 3);

    expect($paginator->total())->toBe(1)
        ->and($paginator->currentPage())->toBe(3);
});

it('does not run the query until it is iterated', function () {
    fakeHttp(['*' => Http::response(taskPage(['1']))]);

    $lazy = Productive::tasks()->query()->lazy();
    Http::assertNothingSent();

    expect($lazy->first()?->id)->toBe('1');
});

it('rejects an empty body where a document is expected', function () {
    fakeHttp(['*' => Http::response('', 200)]);

    Productive::tasks()->find(1);
})->throws(InvalidResponseException::class, 'Productive returned an empty body (HTTP 200) where a JSON:API document was expected.');

it('rejects a resource of the wrong type', function () {
    fakeHttp(['*' => Http::response(['data' => ['type' => 'people', 'id' => '1']])]);

    Productive::tasks()->find(1);
})->throws(InvalidResponseException::class, 'Expected a tasks resource, but Productive returned "people".');

it('rejects a collection where a resource is expected', function () {
    fakeHttp(['*' => Http::response(['data' => []])]);

    Productive::tasks()->find(1);
})->throws(InvalidResponseException::class, 'Expected a single resource');
