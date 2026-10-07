[Back to documentation](README.md)

# Models and relationships

Every response is hydrated into a readonly model such as `Axyr\Productive\Data\Models\Task`.

## Typed properties

```php
$task->id;              // "120501", always a string, as in JSON:API
$task->title;           // ?string
$task->initialEstimate; // ?int, minutes
$task->dueDate;         // ?DateTimeImmutable (calendar date, midnight)
$task->createdAt;       // ?DateTimeImmutable, with offset
$task->tagList;         // ?array
```

All properties are nullable: a sparse response or an unset field is `null`, never an error.

Values are converted when that is lossless (`"480"` → `480`, `"true"` → `true`) and become `null` when not. The untouched value is always available:

```php
$task->attribute('title');        // raw value
$task->attributes();              // every attribute Productive sent, including ones this SDK does not model yet
$task->meta();                    // resource meta, e.g. permissions
$task->toArray();                 // ['id' => …, 'type' => …, 'attributes' => […]]
```

## Relationships

Relationships resolve against the `included` part of the response, so request them with `include`:

```php
$task = Productive::tasks()->find(1, include: ['parent_task', 'attachments']);

$task->parentTask();      // ?Task
$task->attachments();     // list<Model>
$task->related('assignee'); // any relationship, hydrated into its registered model class
```

A relationship that was not included throws a `RelationshipNotIncludedException` telling you which `include()` to add, so "not loaded" is never mistaken for "empty".

The related ID comes from the linkage data, which Productive often sends without an include. When it sent only `"meta": {"included": false}`, `relationshipId()` throws too, rather than returning a misleading `null`:

```php
$task->relationshipId('assignee');     // "12"
$task->relationshipIds('attachments'); // ["7", "8"]
```

Cyclic includes (a task and its parent pointing at each other) are safe: related models are hydrated lazily.

Resource types without a dedicated model class hydrate into `GenericModel`. You can register your own classes:

```php
$this->app->instance(ModelRegistry::class, new ModelRegistry(['people' => MyPerson::class]));
```

---

Previous: [Pagination](pagination.md) · Next: [Creating, updating and actions](writing.md)
