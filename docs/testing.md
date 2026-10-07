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

Operation names are `{resource}.{action}`, e.g. `tasks.index`, `tasks.show`, `tasks.create`, `tasks.update`, `tasks.destroy`, `tasks.reposition`, `time_entries.create_bulk`, `reports.time_reports.index`.

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

### Unseeded requests

Without a seeded response the fake answers plausibly: an empty collection for lists, the submitted resource echoed back with an ID for creates, updates and actions, and 204 where no body is expected. To require every request to be seeded:

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
$fake->recorded();     // list<Request> for anything else
```

## Factories

Factories build realistic models and resource objects, with defaults taken from the examples in Productive's API reference:

```php
$task = TaskFactory::new()->make(['title' => 'Custom']);           // Task model
$tasks = TaskFactory::new()->count(3)->makeMany();                 // list<Task>
$resource = TaskFactory::new()
    ->state(['private' => true])
    ->relatedTo('assignee', 'people', '12')
    ->resource(['id' => '9']);                                     // JSON:API resource object for FakeResponse
```

---

Previous: [Errors, retries and rate limits](errors.md) · Next: [Architecture](architecture.md)
