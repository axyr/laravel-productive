<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\Enums\TimeReportGroup;
use Axyr\Productive\Enums\TimeReportSort;
use Axyr\Productive\Exceptions\InvalidQueryException;
use Axyr\Productive\ProductiveFacade as Productive;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\SpecExamples;

it('fetches a grouped time report (reports-time_reports-index)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('reports-time_reports-index'))]);

    $rows = Productive::reports()->timeReports()->query()
        ->group(TimeReportGroup::Person)
        ->where('after', '2026-03-01')
        ->where('before', '2026-03-31')
        ->sort(TimeReportSort::WorkedTimeDesc)
        ->get();

    expect($rows->first())->toBeInstanceOf(TimeReport::class);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url())
        === apiUrl('reports/time_reports?filter[after]=2026-03-01&filter[before]=2026-03-31&sort=-worked_time&group=person'));
});

it('pages through a report by page number', function () {
    $row = SpecExamples::response('reports-time_reports-index')['data'][0];
    fakeHttp(['*' => Http::sequence()
        ->push(['data' => [$row], 'meta' => ['current_page' => 1, 'total_pages' => 2, 'total_count' => 2]])
        ->push(['data' => [[...$row, 'id' => 'r-019']], 'meta' => ['current_page' => 2, 'total_pages' => 2, 'total_count' => 2]])]);

    $ids = Productive::reports()->timeReports()->query()->group('person')->lazy()->map(fn(TimeReport $row): string => $row->id)->all();

    expect($ids)->toBe(['r-018', 'r-019']);
    Http::assertSentInOrder([
        fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('reports/time_reports?group=person&page[number]=1&page[size]=200'),
        fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('reports/time_reports?group=person&page[number]=2&page[size]=200'),
    ]);
});

it('refuses cursor pagination on reports', function () {
    fakeHttp([]);

    Productive::reports()->timeReports()->query()->after('')->get();
})->throws(InvalidQueryException::class, 'reports/time_reports does not support cursor pagination; use page() instead.');

it('throttles reports to 10 requests per 30 seconds', function () {
    fakeHttp(['*' => Http::response(['data' => [], 'meta' => ['total_pages' => 0]])]);

    for ($i = 0; $i < 11; $i++) {
        Productive::reports()->timeReports()->query()->get();
    }

    Http::assertSentCount(11);
    Sleep::assertSleptTimes(1);
});
