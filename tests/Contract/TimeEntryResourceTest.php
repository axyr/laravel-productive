<?php

declare(strict_types=1);

use Axyr\Productive\Data\Input\CreateTimeEntryData;
use Axyr\Productive\Data\Input\UpdateTimeEntryData;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Exceptions\ConflictException;
use Axyr\Productive\ProductiveFacade as Productive;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\RequestSchema;
use Tests\Support\SpecExamples;

function sentBody(HttpRequest $request): array
{
    return json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
}

function timeEntryCollection(): array
{
    $example = SpecExamples::response('time_entries-show');

    return ['data' => [$example['data'], [...$example['data'], 'id' => '84848215']]];
}

it('lists time entries (time_entries-index)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('time_entries-index'))]);

    $entries = Productive::timeEntries()->query()->where('person_id', 12)->where('after', '2026-03-01')->get();

    expect($entries)->each->toBeInstanceOf(TimeEntry::class);
    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('time_entries?filter[person_id]=12&filter[after]=2026-03-01'));
});

it('shows a time entry (time_entries-show)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('time_entries-show'))]);

    expect(Productive::timeEntries()->find(84848214)->time)->toBe(480);
    Http::assertSent(fn(HttpRequest $request): bool => $request->url() === apiUrl('time_entries/84848214'));
});

it('creates a time entry', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('time_entries-show'), 201)]);

    Productive::timeEntries()->create(new CreateTimeEntryData(serviceId: 1856422, personId: 12, date: '2026-03-15', time: 480, note: 'Auth', taskId: 120501));

    Http::assertSent(function (HttpRequest $request): bool {
        // The spec only documents this endpoint in its bulk variant, but shares the single-resource schema.
        RequestSchema::assertValid('time_entries-create-bulk', sentBody($request));

        return $request->method() === 'POST'
            && $request->url() === apiUrl('time_entries')
            && $request->header('Content-Type') === ['application/vnd.api+json'];
    });
});

it('updates a time entry (time_entries-update)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('time_entries-update'))]);

    Productive::timeEntries()->update(84848214, new UpdateTimeEntryData(note: 'Updated', time: 240));

    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValidAttributes('time_entries-update', sentBody($request));

        return $request->method() === 'PATCH'
            && sentBody($request) === ['data' => ['type' => 'time_entries', 'id' => '84848214', 'attributes' => ['note' => 'Updated', 'time' => 240]]];
    });
});

it('deletes a time entry (time_entries-destroy)', function () {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::timeEntries()->delete(84848214);

    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'DELETE' && $request->url() === apiUrl('time_entries/84848214'));
});

it('runs the approval actions', function (string $method, string $operationId) {
    fakeHttp(['*' => Http::response(SpecExamples::response($operationId))]);

    $entry = Productive::timeEntries()->{$method}(84848214);

    expect($entry)->toBeInstanceOf(TimeEntry::class);
    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'PATCH'
        && $request->url() === apiUrl('time_entries/84848214/' . $method)
        && $request->body() === '');
})->with([
    ['approve', 'time_entries-approve-approve'],
    ['unapprove', 'time_entries-unapprove-unapprove'],
    ['reject', 'time_entries-reject-reject'],
    ['unreject', 'time_entries-unreject-unreject'],
]);

it('reports a conflicting approval state (time_entries-reject 409)', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 409, 'title' => 'Conflict']]], 409)]);

    Productive::timeEntries()->reject(1);
})->throws(ConflictException::class);

it('creates time entries in bulk (time_entries-create-bulk)', function () {
    fakeHttp(['*' => Http::response(timeEntryCollection(), 201)]);

    $entries = Productive::timeEntries()->bulkCreate([
        new CreateTimeEntryData(serviceId: 1, personId: 12, date: '2026-03-15', time: 60),
        ['service_id' => 1, 'person_id' => 12, 'date' => '2026-03-16', 'time' => 30],
    ]);

    expect($entries)->toHaveCount(2)->each->toBeInstanceOf(TimeEntry::class);
    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'POST'
        && $request->url() === apiUrl('time_entries')
        && $request->header('Content-Type') === ['application/vnd.api+json; ext=bulk']
        && sentBody($request) === ['data' => [
            ['type' => 'time_entries', 'attributes' => ['service_id' => 1, 'person_id' => 12, 'date' => '2026-03-15', 'time' => 60]],
            ['type' => 'time_entries', 'attributes' => ['service_id' => 1, 'person_id' => 12, 'date' => '2026-03-16', 'time' => 30]],
        ]]);
});

it('updates time entries in bulk (time_entries-update-bulk)', function () {
    fakeHttp(['*' => Http::response(timeEntryCollection())]);

    Productive::timeEntries()->bulkUpdate([
        84848214 => new UpdateTimeEntryData(note: 'A'),
        '84848215' => ['note' => 'B'],
    ]);

    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'PATCH'
        && $request->header('Content-Type') === ['application/vnd.api+json; ext=bulk']
        && sentBody($request) === ['data' => [
            ['type' => 'time_entries', 'id' => '84848214', 'attributes' => ['note' => 'A']],
            ['type' => 'time_entries', 'id' => '84848215', 'attributes' => ['note' => 'B']],
        ]]);
});

it('deletes, approves and unapproves time entries in bulk', function (string $method, string $httpMethod, string $path) {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::timeEntries()->{$method}([1, '2']);

    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === $httpMethod
        && $request->url() === apiUrl($path)
        && $request->header('Content-Type') === ['application/vnd.api+json; ext=bulk']
        && sentBody($request) === ['data' => [['type' => 'time_entries', 'id' => '1'], ['type' => 'time_entries', 'id' => '2']]]);
})->with([
    ['bulkDelete', 'DELETE', 'time_entries'],
    ['bulkApprove', 'PATCH', 'time_entries/approve'],
    ['bulkUnapprove', 'PATCH', 'time_entries/unapprove'],
]);
