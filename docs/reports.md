[Back to documentation](README.md)

# Reports

Reports live under `Productive::reports()`:

```php
$rows = Productive::reports()->timeReports()->query()
    ->group(TimeReportGroup::Person)
    ->where('after', '2026-09-01')
    ->where('before', '2026-09-30')
    ->sort(TimeReportSort::WorkedTimeDesc)
    ->all();

foreach ($rows as $row) {
    $row->workedTime;            // ?float
    $row->relationshipId('person');
}
```

- `group()` takes the generated enum (`TimeReportGroup`) or a string.
- Reports do not support cursor pagination. `lazy()` and `all()` page by number automatically; `->after()` throws an `InvalidQueryException`.
- Reports have their own rate limit of 10 requests per 30 seconds per token, on top of the general limit. The client-side throttle applies it automatically.
- Productive's advice: **filter narrowly**. Unbounded date ranges on reports are the most expensive calls in the API and use up the organization's processing-time budget.

---

Previous: [Creating, updating and actions](writing.md) · Next: [Errors, retries and rate limits](errors.md)
