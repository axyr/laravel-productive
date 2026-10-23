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

Mutation testing covers the hand-written code: the package core and the generator, about 4,000 mutants, which takes about two hours. Generated classes are left out (`generator/bin/mutate` skips files with the generated marker): their faults come from the emitters, which are mutated. The `drift` group is left out too: it kills a mutant because the generated output changed, not because it is wrong. The nightly mutates the package core (`composer test:mutate -- --scope=core`, about an hour) and must stay at 100%: it blocks pull requests through the CI check. The generator is not part of the nightly, because its output is already pinned by the emitter tests, the drift check, and the input-schema, spec-consistency and hydration tests. Mutate it by hand when changing it, best one file at a time (`composer test:mutate -- --scope=generator`, or Pest's `--path`). The `full-api` group (tests that generate the whole API) is left out of mutation runs too. It runs nightly and on demand in `.github/workflows/mutation.yml`, at a 100% gate, rather than on every pull request. Unless the latest nightly run on `main` succeeded (a failure, time-out or cancellation all count), the "Nightly mutation testing is green" check fails every pull request (while `MUTATION_GATE` in `ci.yml` is `blocking`; `paused` turns the failure into a warning). The pull request that fixes it unblocks itself: start "Mutation testing" manually on its branch, and a successful run for its head commit makes the check pass.

While iterating, scope it to the code you changed, e.g. `--class='Axyr\Productive\Generator\Emit'`. Without `--everything`; that flag overrides `--class`.

Do not add `--parallel`: in parallel mode Pest's mutation plugin reports escaped mutants as killed. The parallel run here claimed 100%, while the real score was 88.6%. `--no-cache` keeps a previous run's results from being reused after tests change.

## Test suites

| Suite | What it covers |
|---|---|
| `tests/Unit` | Every class in isolation, including spec-example hydration of the models. |
| `tests/Feature` | Connector behaviour against Laravel's HTTP fake, pagination, the service provider and the testing fake. |
| `tests/Contract` | One test per API operation through the full stack. URLs, headers and bodies are asserted exactly, responses come from the spec examples, and request bodies are validated against the spec's JSON Schemas. A JSON:API body must carry `data` of the resource's own type, with no attribute the spec does not list. `InputSchemaTest` builds every input class with every attribute and validates the result. |
| `tests/Generator` | The generator: classification rules, every emitter's exact output, and `SpecConsistencyTest`, which recomputes each operation's response kind and organization header from the raw spec and lists the deliberate deviations. |
| `tests/Arch` | The design rules in [Architecture](architecture.md). |

## The spec

Productive's OpenAPI 3.1 spec is vendored at `resources/openapi/productive.json`, downloaded from `https://developer.productive.io/openapi.json`. The per-group download (`/reference/download_spec?group=…`) contains the same component schemas, filtered to one group's paths. Update the vendored file only on purpose, in its own commit.

Every Monday the **Spec drift** workflow (`.github/workflows/spec-drift.yml`) downloads the spec, regenerates everything and opens a pull request when the generated SDK changed, with the operations added and removed and the test result. Productive's export reorders parameters from one download to the next, so a changed file alone opens nothing. A pull request opened with the default token does not start CI: add a fine-grained token as the `SPEC_DRIFT_TOKEN` secret, or close and reopen the PR. Run it by hand from the Actions tab.

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
| Request body | A typed input object for a JSON:API document; a typed input sent as plain JSON when the body is not JSON:API (the four `pages` body actions send `{"html": …}` / `{"markdown": …}`); an attribute array (`array $data`) for creates and updates whose attributes the spec does not document (`integrations`, `proposals`, `resource_requests`, `revenue_distributions`); an optional `array $data = []` for actions documented without a body that cannot work without one: `POST` actions (copies, `generate`) and writes on the whole collection (`merge`); otherwise none. |
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

Generated files start with a "do not edit" marker on its own line in the file header. Change the generator, its config or the spec instead, then run `composer generate`. Files that carry the marker but are no longer produced are deleted. `composer quality`, the pre-commit hook and CI all run `generate:check`.

Generated contract tests call every method with sample arguments. They assert the exact HTTP method, URL, content type and body, validate the body against the spec's schema, and check the hydrated result. Responses come from the spec's examples, with the resource type forced to the one the generator chose.

## Adding resources

Tasks, time entries and the time report were written by hand first and are now generated; they are the reference for review. Every resource in the spec is enabled. When a spec update adds a resource, `ApiTest` fails until its path is added to `generator/config/resources.php`; then run `composer generate` and review the result. Each generated resource consists of:

1. A model in `src/Data/Models`: a typed nullable property per attribute, hydrated in `hydrate()`, plus relationship accessors named after the snake_case relationship.
2. An input class per request body in `src/Data/Input`: required fields first, optional fields defaulting to `Undefined::Value`, and `toAttributes()` mapping to API names.
3. A `final` resource in `src/Resources` with one documented public method per operation, delegating to the `Resource` helpers.
4. Sort and group enums in `src/Enums`.
5. A factory in `src/Testing/Factories` with the spec example's attributes as defaults.
6. A contract test per operation in `tests/Contract`, with the spec example as response and schema validation of the request body.

Because the pattern is fixed, the remaining resources are generated from the spec. A generator restricted to the reference resources must reproduce them byte for byte.

---

Previous: [Architecture](architecture.md)

## Recording real responses

The spec contradicts its own examples in places. `composer record` records what the real API returns, so the SDK can be checked against it.

- **Read-only.** It sends GET requests only: one page of every index (`page[size]=20`), the first record of each, the GET actions that only read (`generator/config/recording-actions.php`: an invoice's preview, a proposal's sync status and signed PDF) on that record, and the index again with every relationship included (`page[size]=5`, saved as `<operation>.include`). If an include fails with 400, the list is split in halves until each relationship that cannot be included is recorded on its own (`<operation>.include.<relationship>`). Resources without an index (a bill, the organization's subscription) are fetched last, by the first ID of their type that any recorded body carried. A publicly shared page is fetched when `RECORDING_PUBLIC_PAGE_UUID` in `.env` holds its uuid. Resources the official Ruby client knows but the spec does not (`generator/config/undocumented.php`) get one GET each, recorded with their status only.
- **Skipped:**
  - writes, and GET actions that act, such as `integrations/{id}/sync`;
  - `sessions` and `passwords`;
  - `public/artifacts`, whose uuid nothing exposes.
- **Guarded.** It runs only when `PRODUCTIVE_ORGANIZATION_ID` equals `RECORDING_ORGANIZATION_ID` (from `.env` or the environment), so it cannot record a client's organization by mistake. It stops on the first 429 and never records one.
- **Gentle.**
  - It sends one request per second, and report requests one per four seconds, on top of Productive's own limits: reports allow 10 per 30 seconds, and the recorder never retries a 429. A full run takes about twelve minutes.
  - Redirects are refused, so the token never leaves the API host.
  - The reports that document `filter[after]`/`filter[before]` (`generator/config/recording-date-filters.php`) are limited to the last month.
- **Local.** Responses are written to `recordings/<date-time>/`, which git ignores. A finished run is marked with a `.complete` file; the commands below only read finished runs.
- **Learning.** Relationships an earlier recording saw without data (in `generator/recorded-types.json`) are included too, so the next run learns their cardinality. String values under keys that look like secrets (`token`, `secret`, `password`, `api_key`, `signature`) are blanked before anything is written. Metadata such as `token_expires_at` or `api_key_id` is kept.

```dotenv
PRODUCTIVE_API_TOKEN=...
PRODUCTIVE_ORGANIZATION_ID=12345
RECORDING_ORGANIZATION_ID=12345
```

```bash
composer record
```

### From recordings to code and tests

Two commands turn a recording into committed files. Neither commits raw responses.

```bash
composer record:types
```

Writes `generator/recorded-types.json`: for every type, the *kinds* of value each attribute had (`int`, `decimal-string`, `date-time`, `list`, …), the relationships seen and their cardinality, the includes the API rejected, and the status of every call. It holds no values, names or IDs, and it is the only recording file the generator reads, so a scrubbing mistake can never change generated code.

`composer generate` applies it under fixed rules:
- An attribute keeps its spec type unless a recorded value is one its lenient reader cannot read. For example, the spec says `int`, the API sends `true`, and the reader would return `null`.
- `null` and empty values never override, so a resource whose list was empty keeps the spec's types.
- `int` and `float` together widen to `float`. A `number` field keeps its float reader even when only whole numbers were seen.
- A list or object the API sends as JSON *text* (a person's `availabilities`) becomes the `json` type: `Attributes::json()` returns it decoded. A field the spec calls a string keeps reading it as a string.
- Attributes and relationships the spec does not document are added and flagged as undocumented. A relationship seen without data has no known cardinality, so it waits for a recording that includes it.
- An attribute that only the spec's example has (its schema does not list it) and that no recorded response of the type carried is kept, always null so far, and listed on the differences page as never seen. The examples look invented in places (`revenue` and `cost` on reports, `url` and `events` on webhooks), but an absence in a recording is not proof, so nothing is dropped until Productive confirms.
- Resources and relationships the API no longer has are listed in `generator/config/removed.php`.

Every difference is listed in the generated [API differences](api-differences.md) page, and the generated [API coverage](coverage.md) page gives every operation a status: recorded, exercised, empty list, rejected, plan-gated, skipped, not recorded or not exercised. A success in the recording always shows as recorded; `generator/config/coverage.php` gives the reasons for the operations the test organization's plan does not include and the ones left out on purpose. The page is done when no row is open.

```bash
composer record:fixtures
```

Writes `tests/Fixtures/Recorded/<type>.json`: per type, the few resources that together show every recorded kind of value, with long lists and maps cut to five entries. They are scrubbed first:
- names, emails, URLs, free text, hashes and UUIDs are replaced;
- IDs are renumbered consistently everywhere (`id`, `*_id`, `*_ids`, `custom_fields` keys);
- keys, JSON types, numbers, booleans, dates and short status codes are kept.

Nothing is written when the output still contains any of these:
- the organization ID (as a whole number) or the API token;
- the git user's name (word by word, any case) or email (the address and its domain);
- the organization's name, email key and billing email, as recorded;
- any term listed in `RECORDING_LEAK_TERMS` in `.env` (comma separated, for anything else you typed into the trial);
- any value the scrubber replaced.

Before the first push of new fixtures, also search them yourself:

```bash
grep -rli -e "<your name>" -e "<your company>" -e "<your organization ID>" tests/Fixtures/
```

It must print nothing.

`RecordedHydrationTest` hydrates every fixture and checks that each non-empty value lands in its typed property unchanged, and that every relationship with data has an accessor. On the models from before the recording it fails for 38 of the 72 types.

### Seeding the recording organization

An empty trial records mostly empty lists, which teach nothing. `composer seed` creates one record of each resource whose list is empty, through the SDK's own create methods:

```bash
composer seed
composer seed -- --execute
```

- **Dry run first.** Without `--execute` it only reads, to find IDs and skip resources that already have records, and prints every create.
- **Same guards as `composer record`:** only the `RECORDING_ORGANIZATION_ID` organization, one request per second, redirects refused, stops on a 429.
- **Logged.** Every payload and response goes to `recordings/seed-<date-time>/`.
- **Nothing that acts outside Productive:** no webhooks, invitations, sending, integrations or custom domains.

It is also the first real check of the write side. It found creates whose required fields the spec does not mark: the payloads in `generator/bin/seed` carry them, with a comment each, and `generator/config/required.php` makes them required in the generated input classes. It also found features a trial plan does not include.
