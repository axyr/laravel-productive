<?php

declare(strict_types=1);

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Data\Input\CreateTaskData;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Exceptions\NotFoundException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Exceptions\StrayRequestException;
use Axyr\Productive\Exceptions\ValidationException;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\Testing\Factories\TaskFactory;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\FakeResponse;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

it('swaps the connector for a recording fake', function () {
    $fake = Productive::fake();
    Http::preventStrayRequests();

    expect(app(ConnectorInterface::class))->toBe($fake->connector())
        ->and(Productive::connector())->toBeInstanceOf(FakeConnector::class);

    Productive::tasks()->delete(1);

    $fake->assertSent('tasks.destroy');
    $fake->assertDeleted('tasks', 1);
    $fake->assertSentCount(1);
});

it('keeps the fake for other organizations and tokens', function () {
    $fake = Productive::fake();

    Productive::withOrganization('2')->withToken('t')->tasks()->delete(5);

    $fake->assertDeleted('tasks', 5);
});

it('returns seeded responses by operation and wildcard', function () {
    Productive::fake([
        'tasks.show' => FakeResponse::resource(TaskFactory::new()->resource(['id' => '9', 'title' => 'Seeded'])),
        'time_entries.*' => FakeResponse::collection(),
    ]);

    expect(Productive::tasks()->find(9)->title)->toBe('Seeded')
        ->and(Productive::timeEntries()->query()->get())->toBeEmpty();
});

it('matches seeded responses by method and path', function () {
    Productive::fake(['GET tasks/*' => FakeResponse::resource(['type' => 'tasks', 'id' => '3', 'attributes' => ['title' => 'By path']])]);

    expect(Productive::tasks()->find(3)->title)->toBe('By path');
});

it('does not match a path pattern with the wrong method', function () {
    Productive::fake(['DELETE tasks/*' => FakeResponse::error(404, 'Record Not Found')])->preventStrayRequests();

    Productive::tasks()->find(3);
})->throws(StrayRequestException::class, 'No fake response seeded for [GET tasks/3] (operation "tasks.show").');

it('builds responses with a callback', function () {
    Productive::fake(['tasks.show' => fn(Request $request) => FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => $request->path]])]);

    expect(Productive::tasks()->find(1)->title)->toBe('tasks/1');
});

it('plays a sequence and fails when it runs out', function () {
    Productive::fake()->respond('tasks.show', FakeResponse::sequence(
        FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'First']]),
        FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'Second']]),
    ));

    expect(Productive::tasks()->find(1)->title)->toBe('First')
        ->and(Productive::tasks()->find(1)->title)->toBe('Second')
        ->and(fn() => Productive::tasks()->find(1))->toThrow(StrayRequestException::class, 'The response sequence for [GET tasks/1] is exhausted.');
});

it('throws the same typed exceptions as the real connector', function () {
    Productive::fake([
        'tasks.show' => FakeResponse::error(404, 'Record Not Found', 'The requested record was not found', 'not_found'),
        'tasks.create' => FakeResponse::validation(['title' => "can't be blank"]),
        'tasks.update' => FakeResponse::rateLimited(30),
    ]);

    expect(fn() => Productive::tasks()->find(1))->toThrow(NotFoundException::class, 'The requested record was not found')
        ->and(fn() => Productive::tasks()->create([]))->toThrow(ValidationException::class, "title can't be blank");

    $exception = null;

    try {
        Productive::tasks()->update(1, []);
    } catch (RateLimitException $caught) {
        $exception = $caught;
    }

    // The fake never retries: a seeded 429 always reaches the caller.
    expect($exception)->toBeInstanceOf(RateLimitException::class)
        ->and($exception?->retryAfter())->toBe(30)
        ->and($exception?->limit())->toBe(100);
});

it('echoes created and updated resources when nothing is seeded', function () {
    $fake = Productive::fake();

    $created = Productive::tasks()->create(new CreateTaskData(title: 'New', projectId: 1, taskListId: 2));
    $second = Productive::tasks()->create(['title' => 'Second']);
    $updated = Productive::tasks()->update(77, ['title' => 'Changed']);
    $approved = Productive::timeEntries()->approve(5);

    expect($created)->toBeInstanceOf(Task::class)
        ->and($created->id)->toBe('1')
        ->and($created->title)->toBe('New')
        ->and($second->id)->toBe('2')
        ->and($updated->id)->toBe('77')
        ->and($updated->title)->toBe('Changed')
        ->and($approved->id)->toBe('5')
        ->and($approved->type)->toBe('time_entries');

    $fake->assertCreated('tasks');
    $fake->assertCreated('tasks', fn(array $attributes): bool => $attributes['title'] === 'New' && $attributes['project_id'] === 1);
    $fake->assertUpdated('tasks', 77);
    $fake->assertUpdated('tasks', '77', fn(array $attributes): bool => $attributes === ['title' => 'Changed']);
});

it('returns empty collections and no content by default', function () {
    Productive::fake();

    expect(Productive::tasks()->query()->all())->toBeEmpty()
        ->and(Productive::reports()->timeReports()->query()->all())->toBeEmpty();

    Productive::tasks()->reposition(1, ['move_after_id' => 2]);
});

it('records requests for custom assertions', function () {
    $fake = Productive::fake();

    Productive::tasks()->query()->where('project_id', 1)->get();
    Productive::tasks()->delete(1);

    expect($fake->recorded())->toHaveCount(2)
        ->and($fake->recorded(fn(Request $request): bool => $request->method === Method::Delete))->toHaveCount(1)
        ->and($fake->recorded()[0]->query)->toBe('filter[project_id]=1');

    $fake->assertSent(fn(Request $request): bool => $request->query === 'filter[project_id]=1');
    $fake->assertNotSent('tasks.create');
    $fake->assertNotSent(fn(Request $request): bool => $request->method === Method::Post);
});

it('fails assertions with clear messages', function (Closure $assertion, string $message) {
    $fake = Productive::fake();
    Productive::tasks()->create(['title' => 'A']);
    Productive::tasks()->update(1, ['title' => 'B']);

    expect(fn() => $assertion($fake))->toThrow(AssertionFailedError::class, $message);
})->with([
    'sent operation' => [fn($fake) => $fake->assertSent('tasks.destroy'), 'No request was sent for operation [tasks.destroy].'],
    'sent callback' => [fn($fake) => $fake->assertSent(fn() => false), 'No matching request was sent.'],
    'not sent operation' => [fn($fake) => $fake->assertNotSent('tasks.create'), 'An unexpected request was sent for operation [tasks.create].'],
    'not sent callback' => [fn($fake) => $fake->assertNotSent(fn() => true), 'An unexpected request was sent.'],
    'count' => [fn($fake) => $fake->assertSentCount(3), 'Expected 3 requests, 2 were sent.'],
    'nothing' => [fn($fake) => $fake->assertNothingSent(), 'Expected 0 requests, 2 were sent.'],
    'created type' => [fn($fake) => $fake->assertCreated('time_entries'), 'No time_entries resource was created.'],
    'created attributes' => [fn($fake) => $fake->assertCreated('tasks', fn(array $attributes) => $attributes['title'] === 'Z'), 'No tasks resource was created with matching attributes.'],
    'updated id' => [fn($fake) => $fake->assertUpdated('tasks', 2), 'tasks 2 was not updated.'],
    'updated attributes' => [fn($fake) => $fake->assertUpdated('tasks', 1, fn(array $attributes) => false), 'tasks 1 was not updated with matching attributes.'],
    'deleted' => [fn($fake) => $fake->assertDeleted('tasks', 1), 'No matching request was sent.'],
    'sent to organization' => [fn($fake) => $fake->assertSentToOrganization('4242', 'tasks.show'), 'No request for operation [tasks.show] was sent for organization 4242.'],
    'sent to organization by callback' => [fn($fake) => $fake->assertSentToOrganization('9999', fn(): bool => true), 'No matching request was sent for organization 9999.'],
]);

it('ignores writes without a resource document in write assertions', function () {
    $fake = Productive::fake();
    Productive::timeEntries()->bulkDelete([1]);
    Productive::timeEntries()->approve(1);

    expect(fn() => $fake->assertUpdated('time_entries', 1))->toThrow(AssertionFailedError::class);
});

it('can allow stray requests again', function () {
    Productive::fake()->preventStrayRequests()->preventStrayRequests(false);

    expect(Productive::tasks()->query()->get())->toBeEmpty();
});

it('reindexes filtered recordings', function () {
    $fake = Productive::fake();
    Productive::tasks()->find(1);
    Productive::tasks()->delete(2);

    expect($fake->recorded(fn(Request $request): bool => $request->method === Method::Delete)[0]->path)->toBe('tasks/2');
});

it('does not count an update as a create', function () {
    $fake = Productive::fake();
    Productive::tasks()->update(1, ['title' => 'A']);

    expect(fn() => $fake->assertCreated('tasks'))->toThrow(AssertionFailedError::class);
});

it('uses the fake even when the facade was resolved before', function () {
    Http::preventStrayRequests();
    Productive::tasks();

    $fake = Productive::fake();
    Productive::tasks()->delete(1);

    $fake->assertDeleted('tasks', 1);
});

it('leaves the host application\'s scoped services alone', function () {
    app()->scoped('host.service', fn() => new stdClass());
    $before = app('host.service');

    Productive::fake();

    expect(app('host.service'))->toBe($before);
});

it('records which organization each request was sent for', function () {
    $fake = Productive::fake();

    Productive::withOrganization('2')->tasks()->delete(5);
    Productive::tasks()->find(6);

    $fake->assertSentToOrganization('2', 'tasks.destroy');
    $fake->assertSentToOrganization('4242', 'tasks.show');
    $fake->assertSentToOrganization('2');

    expect(fn() => $fake->assertSentToOrganization('4242', 'tasks.destroy'))->toThrow(AssertionFailedError::class)
        ->and(fn() => $fake->assertSentToOrganization('2', 'tasks.show'))->toThrow(AssertionFailedError::class)
        ->and($fake->connector()->organizations())->toBe(['2', '4242']);
});

it('records the body as it would go over the wire', function () {
    $fake = Productive::fake();

    Productive::tasks()->create(['title' => 'Plan', 'due_date' => new DateTimeImmutable('2026-10-12 10:00:00+02:00'), 'estimate' => 1.0]);

    expect($fake->recorded()[0]->body)->toBe(['data' => ['type' => 'tasks', 'attributes' => [
        'title' => 'Plan',
        'due_date' => ['date' => '2026-10-12 10:00:00.000000', 'timezone_type' => 1, 'timezone' => '+02:00'],
        'estimate' => 1.0,
    ]]])
        ->and(fn() => Productive::tasks()->create(['title' => "bad \xB1"]))->toThrow(JsonException::class);
});

it('lets respond() override an earlier seed; among fake() seeds the first match wins', function () {
    $fake = Productive::fake([
        'tasks.show' => FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'Specific']]),
        'tasks.*' => FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'Wildcard']]),
    ]);

    expect(Productive::tasks()->find(1)->title)->toBe('Specific');

    $fake->respond('tasks.*', FakeResponse::resource(['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'Override']]));

    expect(Productive::tasks()->find(1)->title)->toBe('Override');

    $fake->respond('projects.index', FakeResponse::collection());

    expect(Productive::tasks()->find(1)->title)->toBe('Override');
});

it('matches path patterns against absolute next-page links', function () {
    $fake = Productive::fake(['GET tasks*' => FakeResponse::sequence(
        FakeResponse::collection([TaskFactory::new()->resource(['id' => '1'])], links: ['next' => apiUrl('tasks?page[after]=c1')]),
        FakeResponse::collection([TaskFactory::new()->resource(['id' => '2'])]),
    )])->preventStrayRequests();

    expect(Productive::tasks()->query()->all()->pluck('id')->all())->toBe(['1', '2']);
    $fake->assertSentCount(2);
});

it('stops when a seeded page keeps returning the same next link', function () {
    Productive::fake(['tasks.index' => FakeResponse::collection([TaskFactory::new()->resource()], links: ['next' => apiUrl('tasks?page[after]=c1')])]);

    expect(fn() => Productive::tasks()->query()->all())->toThrow(Axyr\Productive\Exceptions\InvalidResponseException::class, 'twice');
});

it('answers unseeded public and report requests with their own type', function () {
    Productive::fake();

    expect(Productive::public()->pages()->find('abc'))->toBeInstanceOf(Axyr\Productive\Data\Models\Page::class)
        ->and(Productive::public()->artifacts()->find('abc'))->toBeInstanceOf(Axyr\Productive\Data\Models\Artifact::class);
});

it('fails assertDeleted for another record or another resource', function () {
    $fake = Productive::fake();

    Productive::tasks()->delete(2);
    Productive::timeEntries()->delete(1);

    $fake->assertDeleted('tasks', 2);

    expect(fn() => $fake->assertDeleted('tasks', 1))->toThrow(AssertionFailedError::class)
        ->and(fn() => $fake->assertDeleted('time_entries', 2))->toThrow(AssertionFailedError::class);
});

it('needs a booted application', function () {
    $app = Productive::getFacadeApplication();
    Productive::setFacadeApplication(null);

    try {
        expect(fn() => Productive::fake())->toThrow(LogicException::class, 'Productive::fake() needs a booted Laravel application.');
    } finally {
        Productive::setFacadeApplication($app);
    }
});
