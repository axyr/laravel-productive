[Back to documentation](README.md)

# Pagination

| Method | Returns | Requests |
|---|---|---|
| `get()` | `ModelCollection`: one page, with `meta()`, `links()` and `totalCount()` | 1 |
| `lazy()` | `LazyCollection`: every match, fetched page by page while you iterate | as needed |
| `all()` | `Collection` of every match, in memory | all pages |
| `first()` | the first model, or `null` | 1 (page size 1) |
| `count()` | total number of matches | 1 (page size 1) |
| `paginate($perPage, $page)` | Laravel `LengthAwarePaginator` | 1 |

```php
foreach (Productive::timeEntries()->query()->where('after', '2026-01-01')->lazy() as $entry) {
    // memory stays flat, however many entries there are
}

$paginator = Productive::projects()->query()->paginate(perPage: 25, page: request('page', 1));
```

## Cursor or page numbers

Productive prefers cursor pagination (`page[after]`) for reading whole collections. `lazy()` and `all()` use it automatically, with the maximum page size of 200 unless you set `perPage()`, and follow `links.next` until the last page.

Page numbers are used when cursors cannot be:

- **Reports** do not support cursors; they are paged by number automatically.
- **Sorts the cursor cannot follow**: when Productive answers `keyset_unsupported_sort`, iteration restarts with page numbers.

`lazy()` and `all()` always start at the first page. Combining them with `page()` or `after()` throws an `InvalidQueryException`; use `get()` for a single page.

You can also choose explicitly: `->page(3)->perPage(50)->get()` for one numbered page, or `->after($cursor)->get()` for a cursor page. Combining both throws an `InvalidQueryException`, as Productive would reject it.

`links.next` URLs are only followed when they point at the configured API base URL, so the token is never sent anywhere else.

---

Previous: [Querying](querying.md) · Next: [Models and relationships](models.md)
