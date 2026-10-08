[Back to documentation](README.md)

# Creating, updating and actions

## Input objects

Writes take an input object with named, typed arguments. Required fields have no default:

```php
$task = Productive::tasks()->create(new CreateTaskData(
    title: 'Draft Q3 launch plan',
    projectId: 31002,
    taskListId: 7781,
    assigneeId: 5634,
    dueDate: now()->addWeek(),   // DateTimeInterface or "Y-m-d" string
));
```

## Leaving a field alone versus clearing it

Optional fields default to `Undefined::Value` and are not sent at all. `null` is sent as `null`, which clears the field:

```php
Productive::tasks()->update($id, new UpdateTaskData(title: 'Renamed'));       // only the title changes
Productive::tasks()->update($id, new UpdateTaskData(dueDate: null));          // clears the due date
```

## Raw arrays

Every write also accepts a plain attribute array. Use it for attributes Productive added after this SDK was released:

```php
Productive::tasks()->create(['title' => 'Raw', 'project_id' => 1, 'task_list_id' => 2, 'new_attribute' => true]);
```

## Actions

Endpoints such as `/tasks/{id}/reposition` or `/time_entries/{id}/approve` are methods named after the action:

```php
Productive::tasks()->reposition($id, new RepositionTaskData(moveAfterId: 555));
Productive::tasks()->moveDependent($id, new MoveDependentTaskData(daysCount: 3));
Productive::tasks()->copy(new CopyTaskData(/* … */));
Productive::timeEntries()->approve($id);
Productive::timeEntries()->reject($id);
```

Actions that return the changed resource return its model; actions that answer `204 No Content` return `void`.

## Bulk operations

Resources with a bulk endpoint (`Content-Type: application/vnd.api+json; ext=bulk`) have `bulk*` methods:

```php
Productive::timeEntries()->bulkCreate([$entryA, $entryB]);              // list of inputs or arrays
Productive::timeEntries()->bulkUpdate([84848214 => new UpdateTimeEntryData(note: 'A')]); // keyed by ID
Productive::timeEntries()->bulkDelete([1, 2, 3]);
Productive::timeEntries()->bulkApprove([1, 2, 3]);
```

Writes are never retried after a server error or a dropped connection, because the first attempt may already have been applied. See [Errors](errors.md).

---

Previous: [Models and relationships](models.md) · Next: [Reports](reports.md)
