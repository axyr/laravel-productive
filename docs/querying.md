[Back to documentation](README.md)

# Querying

Every list endpoint starts with `query()`, which returns a fluent `PendingQuery`:

```php
Productive::tasks()->query()
    ->where('project_id', 31002)
    ->include('assignee', 'workflow_status')
    ->sort(TaskSort::DueDateDesc)
    ->get();
```

Nothing is sent until you call a terminal method (`get`, `lazy`, `all`, `first`, `count`, `paginate`). See [Pagination](pagination.md).

## Filters

```php
->where('project_id', 31002)                    // filter[project_id]=31002
->where('project_id', [1, 2, 3])                // filter[project_id]=1,2,3
->where('due_date', '>=', '2026-10-01')         // filter[due_date][gt_eq]=2026-10-01
->where('title', Operator::Contains, 'launch')   // filter[title][contains]=launch
->where('custom_fields.4211', 'Gold')           // filter[custom_fields][4211]=Gold
```

Operators: `eq` (`=`), `not_eq` (`!=`), `contains`, `not_contain`, `gt` (`>`), `gt_eq` (`>=`), `lt` (`<`), `lt_eq` (`<=`). Pass an `Operator` case, its name or the symbol.

Values may be strings, numbers, booleans, backed enums, `DateTimeInterface` (sent as ISO 8601) or lists of those. Anything else, including `null`, throws an `InvalidQueryException` immediately, so a typo never turns into a silently unfiltered request.

Not every endpoint supports operators. Productive answers an unsupported filter with a 400, which this SDK throws as a `BadRequestException`.

### Logical groups

`whereAny()` matches when any condition in the group matches; `whereAll()` when all do. Groups nest:

```php
// name = Productive OR (date > 2024-01-01 AND date = 2023-01-01)
->whereAny(fn (FilterGroup $any) => $any
    ->where('name', 'Productive')
    ->whereAll(fn (FilterGroup $all) => $all
        ->where('date', '>', '2024-01-01')
        ->where('date', '=', '2023-01-01')))
```

A flat set of distinct conditions uses Productive's simple form (`filter[a]=1&filter[b]=2`), which every endpoint supports. Groups, and conditions repeated on one field, use the logical form (`filter[$op]=and&filter[0][…]`).

## Sorting

```php
->sort('due_date')              // ascending
->sort('-due_date', 'title')    // descending, then title
->sort(TaskSort::DueDateDesc)   // generated enum of every documented sort
->orderBy('due_date', 'desc')
```

## Includes

```php
->include('assignee', 'project.company')
```

Included resources become available through the model's relationship accessors; see [Models](models.md). Productive's rate-limit guide advises including only what you use: every relationship adds server time.

## Raw query

`toQueryString()` shows exactly what will be sent:

```php
Productive::tasks()->query()->where('project_id', 1)->toQueryString();
// filter[project_id]=1
```

---

Previous: [Configuration](configuration.md) · Next: [Pagination](pagination.md)
