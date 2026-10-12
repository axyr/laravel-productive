[Back to documentation](README.md)

# Testing

`Productive::fake()` replaces the HTTP layer with an in-memory connector. Your code runs unchanged through the real resources, query building and hydration; only the network is swapped out.

```php
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\Testing\FakeResponse;
use Axyr\Productive\Testing\Factories\TaskFactory;

it('assigns the task', function () {
    $fake = Productive::fake([
        'tasks.show' => FakeResponse::resource(TaskFactory::new()->resource(['id' => '9', 'title' => 'Write docs'])),
    ]);

    (new AssignTask)(taskId: 9, personId: 12);

    $fake->assertUpdated('tasks', 9, fn (array $attributes) => $attributes['assignee_id'] === 12);
});
```

## Seeding responses

Keys are operation names (wildcards allowed) or `"METHOD path"` patterns:

```php
Productive::fake([
    'tasks.show' => FakeResponse::resource($taskOrResourceArray),
    'tasks.*' => FakeResponse::collection([...]),
    'GET time_entries/*' => FakeResponse::error(404, 'Record Not Found'),
    'tasks.update' => fn (Request $request) => FakeResponse::resource([...]),
    'tasks.create' => FakeResponse::sequence(FakeResponse::validation(['title' => "can't be blank"]), FakeResponse::resource([...])),
]);

$fake->respond('reports.time_reports.index', FakeResponse::collection([...]));
```

Among the seeds given to `fake()`, the first one that matches wins, so put specific operations before wildcards. `respond()` takes precedence over every earlier seed, so a test can override a shared setup. `"METHOD path"` patterns also match the absolute next-page links that `lazy()` and `all()` follow: `'GET tasks*'` answers every page.

Operation names follow the API, not the PHP method names: `{path}.{action}`, where the action is the one in Productive's spec. Bulk operations add `_bulk`:

| Method | Operation |
|---|---|
| `query()->get()` / `lazy()` / … | `tasks.index` |
| `find()` | `tasks.show` |
| `create()` / `update()` | `tasks.create` / `tasks.update` |
| `delete()` | `tasks.destroy` |
| `reposition()`, `moveDependent()` | `tasks.reposition`, `tasks.move_dependent` |
| `bulkCreate()`, `bulkApprove()` | `time_entries.create_bulk`, `time_entries.approve_bulk` |
| `reports()->timeReports()->query()` | `reports.time_reports.index` |

The names are derived from the spec, so they stay stable when methods get friendlier names. A stray request's error message always names its operation.

| Helper | Response |
|---|---|
| `FakeResponse::resource($resource, $included)` | single resource document |
| `FakeResponse::collection($resources, $included, $meta, $links)` | collection with pagination meta |
| `FakeResponse::noContent()` | 204 |
| `FakeResponse::binary($contents, $type)` | file download |
| `FakeResponse::error($status, $title, $detail, $code)` | JSON:API error |
| `FakeResponse::validation(['field' => 'message'])` | 422 with source pointers |
| `FakeResponse::rateLimited($retryAfter)` | 429 with `X-RateLimit-Reset` |
| `FakeResponse::sequence(...$responses)` | consecutive responses |
| `FakeResponse::json($document, $status, $headers)` | anything else |

Error responses throw the same typed exceptions as the real connector.

The fake differs from the real connector on purpose in a few ways:
- **It never retries.** A seeded 429 reaches your code as a `RateLimitException` straight away, where the real connector would wait and retry. Seed a 429 to test your own handling of a limit that outlasts the retries.
- **It sends nothing,** so it checks neither credentials nor hosts.
- **It records bodies as they go over the wire.** A body is JSON-encoded and decoded, as the real connector sends it, so a value that cannot be encoded fails in your test too, and a `DateTimeInterface` passed in a plain array is recorded the way PHP encodes it.

### Unseeded requests

Without a seeded response the fake answers plausibly: an empty collection for lists, the submitted resource echoed back with an ID for creates, updates and actions (typed after the resource in the path, also for `public/` paths), and 204 where no body is expected. To require every request to be seeded:

```php
Productive::fake()->preventStrayRequests();
```

## Assertions

```php
$fake->assertSent('tasks.create');
$fake->assertSent(fn (Request $request) => $request->query === 'filter[project_id]=1');
$fake->assertNotSent('tasks.destroy');
$fake->assertSentCount(2);
$fake->assertNothingSent();
$fake->assertCreated('tasks', fn (array $attributes) => $attributes['title'] === 'Write docs');
$fake->assertUpdated('tasks', 9);
$fake->assertDeleted('tasks', 9);
$fake->assertSentToOrganization('2', 'tasks.create');   // after Productive::withOrganization('2')
$fake->recorded();     // list<Request> for anything else
```

`assertCreated()` and `assertUpdated()` look at single-resource writes; check bulk writes with `assertSent()` and the request body.

## Factories

Factories build realistic models and resource objects, with defaults taken from the examples in Productive's API reference:

```php
$task = TaskFactory::new()->make(['title' => 'Custom']);           // Task model
$tasks = TaskFactory::new()->count(3)->makeMany();                 // list<Task>
$resource = TaskFactory::new()
    ->state(['private' => true])
    ->relatedTo('assignee', 'people', '12')
    ->resource(['id' => '9']);                                     // JSON:API resource object for FakeResponse
TaskFactory::new()->count(0)->resources();                         // []
```

Generated IDs count up across a test run, so assert on the IDs you pass in rather than on generated ones.

---

Previous: [Errors, retries and rate limits](errors.md) · Next: [Architecture](architecture.md)
