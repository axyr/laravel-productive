[Back to documentation](README.md)

# Development

## Quality gate

```bash
composer quality
```

The gate runs:

- Pint
- PHPStan at max level
- PHPMD (cyclomatic complexity below 5, methods under 30 lines)
- Pest with **100% line coverage** (needs pcov or Xdebug)
- **100% type coverage**

The pre-commit hook in `.githooks/` runs the same checks and is installed by `composer install`.

Mutation testing:

```bash
composer test:mutate
```

Do not add `--parallel`: in parallel mode Pest's mutation plugin reports escaped mutants as killed. The parallel run here claimed 100%, while the real score was 88.6%.

## Test suites

| Suite | What it covers |
|---|---|
| `tests/Unit` | Every class in isolation, including spec-example hydration of the models. |
| `tests/Feature` | Connector behaviour against Laravel's HTTP fake, pagination, the service provider and the testing fake. |
| `tests/Contract` | One test per API operation through the full stack. URLs, headers and bodies are asserted exactly, responses come from the spec examples, and request bodies are validated against the spec's JSON Schemas. |
| `tests/Arch` | The design rules in [Architecture](architecture.md). |

## The spec

Productive's OpenAPI 3.1 spec is vendored at `resources/openapi/productive.json`, downloaded from `https://developer.productive.io/openapi.json`. The per-group download (`/reference/download_spec?group=…`) contains the same component schemas, filtered to one group's paths. Update the vendored file only on purpose, in its own commit.

Known spec quirks the SDK handles:

- **Bulk operations replace their single-resource twins.** OpenAPI allows one operation per path and method, so `POST /time_entries` only appears as `time_entries-create-bulk`. The single variant uses the same path with `Content-Type: application/vnd.api+json`; the bulk variant uses `; ext=bulk`. The same applies to line items, expense line items, expenses and purchase orders.
- **Create and update share one request schema**, including its `required` list. Update inputs make every field optional.
- **`resource_*` schemas mix attributes with filter-only fields.** Response attributes are taken from the response schemas plus the response examples; request attributes come from the request body schemas.
- **Relationship target types are not in the schema.** They come from the response examples, with a curated map for the rest.
- **Integer enums have no labels in the spec.** The labels are only on the HTML reference pages.
- **Some descriptions are filter text** ("Filter by assigned person") and are rewritten for the attribute docs.

## Adding resources

Tasks, time entries and the time report are the reference resources. Every other resource follows the same pattern:

1. A model in `src/Data/Models`: a typed nullable property per attribute, hydrated in `hydrate()`, plus relationship accessors named after the snake_case relationship.
2. An input class per request body in `src/Data/Input`: required fields first, optional fields defaulting to `Undefined::Value`, and `toAttributes()` mapping to API names.
3. A `final` resource in `src/Resources` with one documented public method per operation, delegating to the `Resource` helpers.
4. Sort and group enums in `src/Enums`.
5. A factory in `src/Testing/Factories` with the spec example's attributes as defaults.
6. A contract test per operation in `tests/Contract`, with the spec example as response and schema validation of the request body.

Because the pattern is fixed, the remaining resources are generated from the spec. A generator restricted to the reference resources must reproduce them byte for byte.

---

Previous: [Architecture](architecture.md)
