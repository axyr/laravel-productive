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

Mutation testing covers the package and the generator, about 4,000 mutants, and takes over an hour. It runs nightly and on demand in `.github/workflows/mutation.yml`, at a 100% gate, rather than on every pull request.

While iterating, scope it to the code you changed, e.g. `--class='Axyr\Productive\Generator\Emit'`. Without `--everything`; that flag overrides `--class`.

Do not add `--parallel`: in parallel mode Pest's mutation plugin reports escaped mutants as killed. The parallel run here claimed 100%, while the real score was 88.6%. `--no-cache` keeps a previous run's results from being reused after tests change.

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

## The generator

`generator/` (not shipped with the package) turns the spec into an intermediate representation (IR): every resource, operation, model and input, already named and typed. P-08 adds the emitters that write PHP from it.

```bash
composer generate:ir
```

writes the IR to `generator/api.json`. `tests/Generator/ApiTest.php` checks it against the spec and the reference resources, and compares it with the committed `generator/api.json`. When the vendored spec or a generator rule changes, run `composer generate:ir`: the diff of that file shows exactly what changes for the SDK.

How the IR is derived:

| Concept | Rule |
|---|---|
| Resource | The path up to its first parameter (`tasks/{id}` → `tasks`). A path without parameters is an action of its parent when the parent is a resource (`tasks/copy`), otherwise a resource itself (`reports/time_reports`). |
| Operation key | `{resource}.{action}`: `index`, `show`, `create`, `update`, `destroy`, the action segment (`reposition`), with `_bulk` for bulk operations. |
| Method | `query`, `find`, `create`, `update`, `delete`, `bulkCreate`, `bulkUpdate`, `bulkDelete`, the camelCase action, `bulk` + action. `generator/config/method-names.php` overrides it per key. |
| Response | Lists return a `ModelCollection`; a body returns the model, or `?Model` when the spec also documents a response without one; `204` returns `void`; an empty `200` or a plain `application/json` response (not JSON:API, e.g. `proposals/{id}/signed_pdf`) returns the raw response. Bulk creates/updates return a collection, bulk deletes/actions `void`. |
| Request body | A typed input object for a JSON:API document; a typed input sent as plain JSON when the body is not JSON:API (the four `pages` body actions send `{"html": …}` / `{"markdown": …}`); an attribute array (`array $data`) for creates and updates whose attributes the spec does not document (`integrations`, `proposals`, `resource_requests`, `revenue_distributions`); otherwise none. |
| Model | One per JSON:API type, read from the show response (else index, else any response with data): schema attributes plus the example's keys, typed by schema, then example, then the name (`*_at` → date-time, `currency*` → string), else `mixed`. Attributes named `id` or `type` become `$idValue` / `$typeValue`. |
| Model type | The example's `type`, unless another resource path owns that type (some examples are copied from other resources). |
| Relationship target | An `owner.relationship` entry in `generator/config/relationship-types.php` (it can correct a wrong example), else seen in an example, else a `relationship` entry there, else the longest trailing part of the name that pluralizes into a known type (`default_tax_rate` → `tax_rates`), else polymorphic (`Model`). A wrong guess fails loudly at runtime, never silently. |
| Input | `Create{Model}Data` / `Update{Model}Data` (all optional) / `{Action}{Model}Data`, required attributes first in spec order, then the rest by name. |
| Synthesized | A bulk create hides its single twin (same path and method), so a `create` is added with the same input: time entries, line items, expense line items. |

## Generating code

```bash
composer generate          # write the code for the enabled resources
composer generate:check    # fail when committed generated code differs from the generator
```

`generator/config/resources.php` lists the resources that are generated. For each one, the generator writes:

- the resource class
- its model and factory
- its input classes
- its sort/group enums
- one contract test per operation, in `tests/Contract/Generated`

It also writes the shared entry points:

- `Concerns\ProvidesResources` (the client's accessors)
- the `Reports` and `PublicResources` groups
- the facade's docblock
- `ModelMap`

Generated files start with a "do not edit" marker. Change the generator, its config or the spec instead, then run `composer generate`. Files that carry the marker but are no longer produced are deleted. `composer quality`, the pre-commit hook and CI all run `generate:check`.

Generated contract tests call every method with sample arguments. They assert the exact HTTP method, URL, content type and body, validate the body against the spec's schema, and check the hydrated result. Responses come from the spec's examples, with the resource type forced to the one the generator chose.

## Adding resources

Tasks, time entries and the time report were written by hand first and are now generated; they are the reference for review. To add a domain, add its resource paths to `generator/config/resources.php`, run `composer generate`, and review the result. Each generated resource consists of:

1. A model in `src/Data/Models`: a typed nullable property per attribute, hydrated in `hydrate()`, plus relationship accessors named after the snake_case relationship.
2. An input class per request body in `src/Data/Input`: required fields first, optional fields defaulting to `Undefined::Value`, and `toAttributes()` mapping to API names.
3. A `final` resource in `src/Resources` with one documented public method per operation, delegating to the `Resource` helpers.
4. Sort and group enums in `src/Enums`.
5. A factory in `src/Testing/Factories` with the spec example's attributes as defaults.
6. A contract test per operation in `tests/Contract`, with the spec example as response and schema validation of the request body.

Because the pattern is fixed, the remaining resources are generated from the spec. A generator restricted to the reference resources must reproduce them byte for byte.

---

Previous: [Architecture](architecture.md)
