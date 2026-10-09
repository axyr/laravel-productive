# axyr/laravel-productive — investigation & build plan

Target repo: https://github.com/axyr/laravel-productive (empty, no commits yet)
Reference package: https://github.com/axyr/laravel-langfuse
API: https://developer.productive.io — base URL `https://api.productive.io/api/v2/`
Local development: `/Users/martijn/www/packages/productive`

**Decisions (2026-10-07)**
- **Exceptions, not nulls.** Every non-2xx response throws a typed exception. Stability and correctness come first.
- **PHP 8.3+**, Laravel 12 | 13.
- **Operation keys follow the spec, not the PHP method names** (2026-10-08). The format is `{path}.{spec action}`, with `_bulk` for bulk operations, e.g. `tasks.destroy` and `time_entries.create_bulk`. Users type these keys in `fake()` and `assertSent()`. The generator must derive them from the spec's operationIds, so they stay stable if methods are renamed.
- **No sandbox yet.** Fixtures and test cases come from the spec and docs examples. The live suite is scaffolded but stays dormant until a [Productive sandbox](https://help.productive.io/en/articles/9448080-sandbox) exists (P-23).
- **Spec source:** `https://developer.productive.io/openapi.json`. The per-group download (`/reference/download_spec?group=…`) returns the same component schemas, filtered to one group's paths, so the full JSON is the one to vendor.

---

## 0. Status (2026-10-07)

**P-01 to P-06 are built locally** in `/Users/martijn/www/packages/productive`. Nothing is committed yet.

Quality gate: all green.
- Pint
- PHPStan level max
- PHPMD (langfuse ruleset, no exclusions)
- 445 Pest tests
- **100% line coverage**, **100% type coverage**
- Mutation testing: **1,455 of 1,463 mutants killed; the other 8 time out**. A timeout means the mutant made the code loop forever, so the tests did catch it.

As built, these differ from the original plan:
- **Mutation testing** needs `--everything --covered-only` and must **not** run with `--parallel`. In parallel mode Pest reported 100% while the real score was 88.6%. Closing the real gaps found and fixed:
  - a real data-loss bug (`lazy()->all()` overwrote earlier pages)
  - `include('-x')` was accepted
  - integer enums were not read from numeric strings
  - redundant code, which was simplified away

  The composer script and CI run non-parallel with a 100% gate.
- **The input base class is `Data\InputData`**, not `Data\Input`, because a class name equal to a namespace confused the arch rules.
- **The fake works at connector level.** `Productive::fake()` swaps `ConnectorInterface`, so tests run the real resources, query building and hydration. Operation names are `{resource}.{action}` (`tasks.reposition`, `time_entries.create_bulk`, `reports.time_reports.index`).
- **Pagination falls back on its own.** `lazy()`/`all()` use cursors, fall back to page numbers on `keyset_unsupported_sort`, and use page numbers for reports. Cursor plus page number on a report throws client-side.
- **Generator findings to feed into P-07 and P-08:**
  - **Bulk/single collisions.** OpenAPI allows one operation per path and method, so `POST /time_entries` only exists as `time_entries-create-bulk`. The single-item operations for time entries, line items, expense line items, expenses and purchase orders must be synthesised (same path, plain content type).
  - **Relationship target types are not in the schema** (every relationship is `_single_relationship`). Derive them from response examples and add a curated override map.
  - **Integer enum labels are only on the HTML reference pages** (e.g. billing type 1 = Fixed). Scrape them into `generator/enum-labels.json`.
  - **Create and update share one request body** including `required`. Generate `Create*Data` with the required fields and `Update*Data` with everything optional. Contract tests validate update bodies attribute by attribute.
  - **Untyped response attributes.** Use the schema type, then infer from the response example, then fall back to `mixed`. Attributes ending in `_at` become date-times.
  - **Response attributes** are the response schema plus response example keys (e.g. task `description` is only in the example).
  - **Filter text in attribute descriptions** ("Filter by assigned person") needs a cleanup map.
  - **Attribute names that collide with `Model` members** (`id`, `type`) must be renamed.
  - Generated classes carry `@SuppressWarnings` for PHPMD length and parameter-count rules (as the golden ones do). `phpstan.neon.dist` treats `Model::hydrate` as a constructor.
- **P-07 results (2026-10-08):**
  - The IR covers all 668 spec operations plus 3 synthesized single creates: 140 resources and 137 models.
  - It matches the golden resources exactly: methods, keys, return types, model properties, relationship accessors, inputs and enums.
  - More spec quirks the generator handles:
    - Example types copied from other resources (`agent_roles` claims `roles`, `integration_exporter_configurations` claims `integrations`, `timesheet_reports` claims `timesheets`).
    - Attributes named `id`/`type` (`$idValue`, `$typeValue`).
    - 17 operations documenting both a body and no body (`?Model`).
    - One empty `200` (raw response).
  - Relationship targets: 749 of 849 are typed. The other 100 are polymorphic or unknown and return `Model`.
  - Typed accessors now **throw** on a type mismatch instead of returning null, so a wrong guess can never silently lose data.
- **Phase 3, done as 3 grouped PRs (2026-10-09):**
  - **A — work management:** tasks and docs, projects and resource planning, time tracking, CRM.
  - **B — money:** financials, invoicing, reports, dashboards.
  - **C — organization:** organization, admin, auth and public endpoints.
  - **A changes the generator for every domain:**
    - Actions documented without a body that cannot work without one get `array $data = []`. That covers the copy/generate `POST`s and the collection-level `merge` writes.
    - Inputs keep `type`/`id` as property names (only models need `$typeValue`).
    - Object maps record their value type (`array<string, int>`).
    - Factories drop `id`/`type` attributes, which JSON:API forbids.
  - Coverage tests now iterate every generated model and factory.
  - **B** adds a `bulk_item` body kind: `deals.copy` only accepts a bulk document (`data: [...]`, `ext=bulk`) even though it copies one deal.
  - **C** enables the remaining 40 resources, so every resource in the spec is generated (`ApiTest` fails when a spec update adds one that is not listed).
    - The `public/*` endpoints (uuid links to shared pages, artifacts and proposals) skip the organization header. The spec marks them unauthenticated; the SDK still requires and sends the token, which only goes to the configured host.
    - Contract tests now resolve inline request bodies (`approval_statuses` approve/reject) as well as `$ref`s.
- **Phase 4: verification against the real API (agreed 2026-10-09).**
  - **Why:** a test audit found that 100% line coverage hid weak checks.
    - For 134 of 137 models, no test checks hydrated values. 23 attributes always hydrate as `null` because the spec's schemas contradict its own examples.
    - The generated request checks mostly compare the code with the IR it was generated from.
    - The generator's mutation score comes mostly from checksum snapshots.
  - **One PR per step:**
    1. Security and runtime fixes. Redirects are never followed, because Guzzle forwards `X-Auth-Token`. A repeated next link throws instead of looping. Plus tests for http downgrades, the throttle scope and config wiring.
    2. A read-only recorder (`composer record`), built in #9:
       - GET `index`/`show` and the reports, taken from the IR. GET actions such as `integrations/{id}/sync` are skipped, as are `sessions`, `passwords` and `public/*`.
       - The include call uses `page[size]=5` and splits a failing include list in halves to isolate the relationships that cannot be included.
       - It refuses to run unless the organization matches `RECORDING_ORGANIZATION_ID`, stops on the first 429, and never stores a 429.
       - It paces one request per second and refuses redirects. The 6 reports that document `after`/`before` are limited to the last month.
       - Secret strings are redacted before anything touches disk. Responses are saved to `recordings/<date-time>/`, which is gitignored. Nothing recorded is committed.
       - It also probes the 17 resources the Ruby client knows but the spec does not, recording only the status code.
       - The trial organization is seeded first, so the index calls return data.
       - Committing anything from the recordings (scrubbed fixtures, a leak check) is decided after the first run.
    3. Recorded types in the generator.
       - Rules: `null` and empty lists never override the spec; int vs float widens to float; an override needs a non-null value that the lenient reader cannot read.
       - `docs/api-differences.md` lists every override.
       - Tests hydrate every recorded resource and check each value, and every recorded relationship must have an accessor. This fixes the 23 conflicts and the 29 missing relationships.
    4. Strict request validation: a `data` wrapper and the right `type` are required, unknown keys fail, every input class is validated in full, and the response kind and organization header are recomputed from the raw spec.
    5. Fake fixes:
       - The seeded paging loop, public page and artifact defaults, and JSON-encoding recorded bodies.
       - Seed precedence, recording which organization a request went to, and the vacuous rate-limit test.
    6. Honest generator tests:
       - A mutation run without the drift group, with survivors pinned by unit tests.
       - Tests for the test helpers, and honest names for the "golden" tests.
    7. Mutation scope, **decided 2026-10-09: hand-written code only.**
       - The baseline nightly before Phase 3 took 2 h 6 min for 4,034 mutants. With all generated code the run would need about 12 hours of runner time every night.
       - `generator/bin/mutate` (`composer test:mutate`, and the nightly) mutates the 108 files without the generated marker: the package core and the generator, emitters included. A fault in generated code comes from an emitter.
       - The caveat from the audit: the contract tests check requests in full, but responses only by class. Generated models get value tests in step 3. Until then, generated code has neither value tests nor mutants.
       - `MUTATION_GATE` in ci.yml goes back to "blocking" once a manual run of the scoped workflow on main is green.
- **P-08 acceptance, sharpened:** a generator restricted to the three golden tags must reproduce the golden files byte for byte. The golden models only have typed relationship accessors for models that already exist (Task, TimeEntry); others return `Model`. Once every model exists, all accessors are typed.
- **The prototype emitter rules** (type mapping, naming, ordering, docblocks) are written down in `docs/development.md` → *Adding resources*. The generator must follow them.

## 1. What we found

### The API surface

Productive publishes a machine-readable **OpenAPI 3.1 spec** at
`https://developer.productive.io/openapi.json` (4.2 MB). That spec is the source of truth for "all endpoints":

| | Count |
|---|---|
| Paths | 404 |
| Operations | **668** (247 GET, 226 PATCH, 115 POST, 79 DELETE, 1 PUT) |
| Tags (resource groups) | 117 |
| Resource schemas / request bodies / responses | 152 / 151 / 259 |
| Standard CRUD ops (`index/show/create/update/destroy`) | 462 |
| Custom actions (`archive`, `restore`, `approve`, `copy`, `move`, `reposition`, `send`, `finalize`, `merge`, …) | 206 |
| Bulk ops (`Content-Type: application/vnd.api+json; ext=bulk`) | 21 (time entries, line items, expense line items, expenses, purchase orders) |
| Report endpoints (`/reports/*`, with `group` param) | 26 |
| Public endpoints (no org header, `{uuid}` path) | 5 (`public/artifacts`, `public/pages`, `public/proposals/{uuid}/accept`, …) |
| Non-JSON responses (`any`, e.g. signed PDF) | 6 |

The doc categories map onto the tags like this (the spec also has ~35 tags that sit in no category: agents, approvals, custom fields, organizations, users, sessions, webhooks, notifications, etc.):

- **CRM:** companies, contact entries, deal statuses, lost reasons, people, pipelines
- **Docs:** discussions, pages, page versions
- **Financials:** bank accounts, bills, contracts, deal cost rates, deals, document styles/types, exchange rates, expenses (+ line items, bulk), overheads, prices, proposals, purchase orders (+ bulk), rate cards, revenue distributions, salaries, sections, service (type) assignments, service types, services, tax rates
- **Invoicing:** automatic invoicing rules, e-invoice identities, invoice attributions, invoice templates, invoices, line items (+ bulk), payment reminder sequences, payments
- **Project management:** placeholders, placeholder usages, project preferences, projects
- **Reports:** dashboards, pulses, report categories, reports (26), widgets
- **Resource management:** bookings, entitlements, events, holiday calendars, holidays, resource requests
- **Tasks:** folders (boards), task lists, task dependencies, tasks, todos, workflow statuses, workflows
- **Time tracking:** time entries (+ bulk), time entry versions, time tracking policies, timers, timesheets

### API conventions the SDK must handle

- **JSON:API** (`application/vnd.api+json`): `data / attributes / relationships / included / meta / links`.
- **Auth:** `X-Auth-Token` header + required `X-Organization-Id` header on 663 of 668 operations.
- **Filtering:** `filter[field]=v`, operators `filter[field][op]=v` (`eq, not_eq, contains, not_contain, gt, gt_eq, lt, lt_eq`), and nested logical groups (`filter[$op]=or&filter[0][name][eq]=…&filter[1][$op]=and…`). Values must be strictly URL-encoded (`+` → `%2B`). Unsupported filters return 400.
- **Sorting:** `sort=a,-b`. The allowed values per resource are enumerated in the spec, so we can generate an enum for each.
- **Includes:** `include=assignee,project`.
- **Pagination:** cursor pagination is the preferred mode (`page[after]=&page[size]=200`, then follow `links.next`). Page-based (`page[number]`, `page[size]`, max 200) is still needed for reports, which do not support cursors. A cursor sent together with `page[number]` returns an error (`keyset_conflict` / `keyset_unsupported_sort`).
- **Reports:** `group=` (enum per report) + filters + sort.
- **Feature flags header:** `X-Feature-Flags: filteringSkipDatetimeCastToDate` (datetime filters).
- **Rate limits** (all return 429):
  - 100 req / 10 s per token
  - 4,000 req / 30 min per org
  - `/reports`: 10 req / 30 s per token
  - Processing-time budgets: 30 min/hour and 6 h/day of server time
  - Some per-endpoint limits (salaries: 30 / 2 min; contract generation: 50 / min)
  - The `X-RateLimit-Reset` header gives the seconds until reset.
- **Errors:** JSON:API `errors[]` with `status/title/detail` (and `source` on 422). Statuses seen in the spec: 400, 401, 402, 403, 404, 405, 406, 409, 410, 415, 422, 429, 5xx.

### Spec quality caveats (the generator must cope with these)

- `resource_*` schemas mix real attributes with **filter-only** fields (e.g. `due_date_new`, `after` on tasks) and carry filter-style `example`s. → Derive **response attributes** from the `collection_*`/`single_*` response schemas (which `$ref` the subset that is actually returned) and **request attributes** from `requestBodies`, never from `resource_*` directly.
- Some `enum`s are integer codes with no labels (e.g. `type_id: [1, 3]`). We need a small hand-maintained label map for the important ones; the rest stay as `int`.
- The docs site lists a few resources (deal contacts, KPD codes, e-invoice transactions) that have no paths in the spec. **The spec wins**, and we note the gap in the docs.
- Guides are served as markdown at `/guides/{slug}` (overview, pagination, rate-limits, filtering, sorting, error-handling). We vendor them as reference.

### Fixture material (in place of a sandbox)

| Source in the spec | Available |
|---|---|
| Full example documents on 2xx responses | 549 |
| Request-body examples | 206 |
| JSON code blocks in operation descriptions | 22 |
| Resource properties with an `example` value | 5,268 / 5,710 (92%) |
| Error shapes (400/401/403/404/406/415/422/429/500) | from the error-handling and rate-limits guides |

The generator extracts these into `tests/Fixtures/Spec/{operationId}.{request,response}.json`. Operations without a full example get a document synthesised from the per-property examples, and is flagged `synthesised` so we can replace it with a real recording once the sandbox exists.

**Caveat:** spec examples are written by Productive, not recorded from the API, so they can drift from real responses. The models therefore keep `raw` attributes and never fail on unknown fields. The sandbox live suite (P-23) is the final check.

### What we carry over from laravel-langfuse (and what we deliberately change)

**Keep:**
- `Axyr\` namespace
- Laravel 12 | 13 (PHP raised to **8.3+**)
- Readonly config object with `fromArray()` parsing
- Contracts as interfaces
- Readonly DTOs with no Laravel dependency
- Facade + ServiceProvider with auto-discovery
- Pest + Testbench, Pest arch tests
- Larastan, Pint (`per` + strict types), PHPMD with the same ruleset
- `composer quality`, the `.githooks/pre-commit` gate and the CI job layout
- The `docs/` folder + README structure
- A first-class `Testing/` fake with assertions
- Octane-safe scoped bindings

**Change (on purpose):**
- **Fail loud, not silent.** Langfuse swallows errors and returns `null` because observability must never break the host app. A CRUD/finance SDK must not do that: a silently failed `invoices()->finalize()` is a bug. We throw typed exceptions.
- **Generated, not hand-written.** 668 operations across 117 resources cannot be hand-written to a consistent quality standard. We hand-write the core plus three "golden" resources. A committed generator then emits the rest from the vendored spec, and CI fails on drift.
- **Coverage is enforced, not implied.** Langfuse CI runs with `coverage: none`. Here CI runs pcov and gates on 100% line coverage, 100% type coverage, and mutation score.

---

## 2. Architecture

```
src/
  Productive.php                 Entry point (resolved by the facade): resource accessors, withOrganization(), withToken()
  ProductiveFacade.php           Productive::tasks()->…   (@method docblocks generated)
  ProductiveServiceProvider.php
  Config/ProductiveConfig.php    readonly: token, organizationId, baseUrl, timeout, retries, throttle, featureFlags
  Contracts/                     ConnectorInterface, ProductiveClientInterface, …
  Http/
    Connector.php                The ONLY class touching Illuminate\Http\Client (arch-test enforced)
    RateLimiter.php              Client-side token buckets (100/10s, reports 10/30s), Cache-backed so queue workers share them
    RetryPolicy.php              429 → sleep X-RateLimit-Reset, 5xx → backoff, configurable max attempts
    Request.php / Method.php     Immutable request value object (method, path, query, body, contentType)
  JsonApi/
    Document.php                 Parse top-level document
    ResourceObject.php           id, type, attributes, relationships, links, meta
    IncludedResolver.php         Identity map; hydrates relationships from `included` (cycle-safe)
    DocumentBuilder.php          Builds {data:{type,attributes,relationships}}, bulk ext arrays
    ErrorObject.php
  Query/
    Query.php                    Fluent: where(), orWhere(), whereGroup(), sort(), include(), page(), size(), cursor(), group()
    Operator.php (enum)          eq, not_eq, contains, not_contain, gt, gt_eq, lt, lt_eq
    QuerySerializer.php          deepObject + logical groups, RFC 3986 encoding
  Pagination/
    CursorPaginator.php          → LazyCollection that follows links.next (default for ->all()/->lazy())
    PagePaginator.php            → Illuminate LengthAwarePaginator adapter (reports, "jump to page")
  Resources/
    Resource.php                 Abstract base: list/find/create/update/delete/action/bulk helpers
    Concerns/                    Hand-written overrides for special endpoints (binary, markdown pages, sessions/OTP, public uuid)
    TaskResource.php …           GENERATED, one per path resource (≈110)
    Reports/TimeReportResource.php …  GENERATED (26)
    Public/PublicProposalResource.php … GENERATED
  Data/
    Model.php                    Base: id, type, typed attributes, raw attributes (forward-compatible), relationships, meta
    Task.php …                   GENERATED readonly response models with typed props + relation accessors
    Input/CreateTaskData.php, UpdateTaskData.php …  GENERATED request DTOs; `Missing` sentinel so PATCH distinguishes "not sent" from null
    Collection.php               Typed collection + meta + links
  Enums/                         GENERATED: TaskSort, TimeReportGroup, …; hand-labelled int enums where useful
  Exceptions/                    ProductiveException (base, RuntimeException)
                                 → ValidationException (422, errors w/ source pointers), NotFoundException,
                                   AuthenticationException (401), AuthorizationException (403), ConflictException (409),
                                   RateLimitException (429, retryAfter, limit, period), UnsupportedQueryException (400),
                                   ServerException (5xx), PaymentRequiredException (402), GoneException (410)
  Webhooks/                      Inbound receiver: route macro, controller, verification middleware, ProductiveWebhookReceived event
  Testing/
    ProductiveFake.php           Productive::fake(); records requests; returns seeded or factory responses
    ResponseFactory.php          collection()/single()/error() JSON:API documents
    Factories/TaskFactory.php …  GENERATED model factories built from spec examples
    Concerns/AssertsRequests.php assertSent(), assertCreated('tasks', fn), assertUpdated, assertDeleted, assertActionCalled, assertNothingSent
  Console/                       productive:ping (verify token + org), productive:spec-check (local drift report)
generator/                       NOT shipped (export-ignore): spec loader → IR → emitters (nette/php-generator) → pint
resources/openapi/productive.json   vendored spec (pinned; updated only via drift PR)
```

### Developer experience (target API)

```php
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\Query\Operator;

// Cursor-paginated, lazy: follows links.next, page[size]=200
Productive::tasks()->query()
    ->where('project_id', 31002)
    ->where('due_date', Operator::GtEq, '2026-10-01')
    ->include('assignee', 'workflow_status')
    ->sort(TaskSort::DueDateDesc)
    ->lazy()
    ->each(fn (Task $task) => $task->assignee()?->name);

$task = Productive::tasks()->find(5560100, include: ['project']);

$task = Productive::tasks()->create(new CreateTaskData(
    title: 'Draft Q3 launch plan',
    projectId: 31002,
    taskListId: 7781,
    workflowStatusId: 101,
));

Productive::tasks()->update($task->id, new UpdateTaskData(dueDate: null)); // explicit null is sent; omitted fields are not
Productive::tasks()->reposition($task->id, moveAfterId: 555);
Productive::invoices()->finalize($invoiceId);
Productive::timeEntries()->bulkCreate([$a, $b, $c]);

Productive::reports()->timeReports()
    ->group(TimeReportGroup::Person)
    ->where('date', Operator::GtEq, '2026-09-01')
    ->paginate(perPage: 100);                     // reports: page-based only

Productive::withOrganization('other-org-id')->projects()->query()->lazy();   // multi-org, e.g. for Taskforce

// Tests
Productive::fake(['tasks.create' => TaskFactory::new()->make(['title' => 'x'])]);
Productive::assertCreated('tasks', fn (array $attributes) => $attributes['title'] === 'x');
```

### Key design rules (arch-tested)

1. Only `Http\Connector` uses the Laravel HTTP client.
2. `Data\*` and `JsonApi\*` depend on nothing in Laravel.
3. Everything in `Data\`, `Config\` and `Http\Request` is readonly.
4. Every exception extends `ProductiveException`, which extends `RuntimeException`.
5. Every generated resource extends `Resources\Resource` and is `final`.
6. Generated files start with a "generated — do not edit" header. Customisation only goes through `generator/overrides.php` + `Resources/Concerns/*`.
7. Strict types everywhere; no `mixed` leaks into public signatures except the raw-attributes escape hatch.

---

## 3. Quality bar (definition of done for every task)

- `composer quality` green: Pint `--test`, Larastan **level max** on `src/` (and level 9 on `generator/`), PHPMD (langfuse ruleset), Pest.
- **100% line coverage**: `pest --coverage --min=100`, run with pcov in CI.
- **100% type coverage**: `pest --type-coverage --min=100`.
- **Mutation testing** on hand-written code (`Http`, `JsonApi`, `Query`, `Pagination`, `Resources\Resource`, `Testing`, `Webhooks`): `pest --mutate --min=90`.
- **Endpoint completeness gate** (`tests/Contract/SpecCoverageTest.php`): parses the vendored spec and asserts that every `operationId` maps to an SDK method that has a contract test. It ships with an allowlist of not-yet-generated tags, which must be **empty** at the end of Phase 3.
- **Contract tests per operation (generated):** assert HTTP method, path, headers (`X-Auth-Token`, `X-Organization-Id`, content type incl. `ext=bulk`), query serialisation, request body shape, response hydration and error mapping. Request bodies are also validated against the spec's JSON Schema (`opis/json-schema`).
- **Generator drift check:** `composer generate:check` regenerates into a temp dir and diffs against `src/` and `tests/`. It runs in CI and fails on any difference.
- **Docs:** every public feature has a page in `docs/`; the endpoint reference is generated.
- **Spec-fixture tests:** every operation's contract test runs against the extracted spec example (request and response), so all 668 operations have a realistic fixture from day one.
- **Live smoke suite** (dormant until the sandbox exists; `tests/Live`, skipped unless `PRODUCTIVE_LIVE_TOKEN` + `PRODUCTIVE_LIVE_ORG` are set): read-only `index`/`show` across all resources, plus a create→update→delete round-trip on safe resources. Sandbox org only, never in default CI.
- CI matrix: PHP 8.3 / 8.4 / 8.5 × Laravel 12 / 13 × prefer-lowest / prefer-stable. Coverage + mutation run on one leg.

---

## 4. Taskforce backlog

Each task is one branch + PR, sized for one developer run (opus, 120 turns), with QA gating on `composer quality`. Tasks only depend on earlier ones. **P-05 is a human review checkpoint:** it freezes the patterns the generator will reproduce 600+ times.

### Phase 0 — Foundation

**P-01 Scaffold & tooling** — ✅ built
- composer.json (`axyr/laravel-productive`, `Axyr\Productive\`, PHP ^8.3, Laravel ^12|^13), copying the langfuse dev toolchain plus `pestphp/pest-plugin-type-coverage`.
- pint.json, phpstan.neon.dist (max), phpmd.xml, phpunit.xml.dist (with a `Contract` suite and a `Live` suite excluded by default), `.githooks/pre-commit`, `.gitattributes` (export-ignore `generator/`, `tests/`, `docs/`, `art/`), and a CI workflow with the matrix + coverage/type/mutation jobs.
- `ProductiveConfig`, `config/productive.php` (`PRODUCTIVE_API_TOKEN`, `PRODUCTIVE_ORGANIZATION_ID`, `PRODUCTIVE_BASE_URL`, timeout, retry, throttle, feature flags), the ServiceProvider (publishable config, scoped bindings), the facade stub, and the Version class.
- Vendor the spec to `resources/openapi/productive.json` and the guides to `docs/vendor/`.
- Skeleton README + docs index, CHANGELOG.
- Acceptance: green `composer quality` at 100% coverage on the tiny surface; arch tests in place.

**P-02 HTTP transport, errors & rate limiting** — ✅ built
- `Connector`, the `Request` value object, header injection, timeouts.
- JSON:API error parsing → the exception hierarchy (every status in the spec).
- `RetryPolicy`: 429 honouring `X-RateLimit-Reset`, capped; 5xx with exponential backoff; idempotency-aware (no auto-retry of POST unless opted in).
- `RateLimiter`: Cache-backed client-side buckets (token 100/10s, reports 10/30s, per-endpoint overrides for salaries/contracts) so multiple queue workers share one budget.
- Hooks: Laravel `Http` events plus an optional log channel with the token redacted.
- Acceptance: every status → exception tested; retry/throttle tested with a fake clock (`Sleep::fake()`); mutation ≥ 90%.

**P-03 JSON:API document layer** — ✅ built
- `Document`, `ResourceObject`, `ErrorObject`, `IncludedResolver` (identity map, nested + cyclic includes), `DocumentBuilder` (attributes, relationships, bulk `ext=bulk` arrays), `Collection` with meta/links.
- Acceptance: fixtures taken from spec examples; cyclic include test; 100% / mutation ≥ 90%.

**P-04 Query builder, serializer & pagination** — ✅ built
- `Query` (where, operators, nested and/or groups, sort, include, page/size, cursor, group, feature flags), `QuerySerializer` (deepObject, RFC 3986, `+`→`%2B`, booleans/dates/arrays/enums).
- `CursorPaginator` → `LazyCollection` via `links.next`; `PagePaginator` → `LengthAwarePaginator`. Guards: cursor + number conflict and cursor on reports throw a clear exception client-side.
- Acceptance: golden query-string tests for every example in the filtering/pagination guides.

### Phase 1 — Golden pattern (hand-written, human-reviewed)

**P-05 Base resource + three golden resources** ⚠ human review checkpoint — ✅ built, awaiting your review
- `Resources\Resource` base (list/query/find/create/update/delete/action/bulk). `Data\Model` base, the `Missing` sentinel, input DTOs.
- Hand-write **Tasks** (CRUD + `copy`, `reposition`, `move_dependent`), **TimeEntries** (CRUD + approve/reject/… + all 5 bulk ops) and **Reports\TimeReports** (group + page pagination).
- These three are the template: one standard resource, one with bulk and actions, one report.
- Acceptance: contract tests for all 25-ish ops of these three, using spec-example fixtures; the docs page "Resources: the pattern"; **sign-off from you before P-07 starts.**

**P-06 Testing fake** — ✅ built
- `Productive::fake()`, `ResponseFactory`, request recording, the assertions listed above, hand-written factories for the golden models, and support for `Http::preventStrayRequests()`.
- Acceptance: the fake implements the client contract (arch test); `docs/testing.md`.

### Phase 2 — Generator

**P-07 Spec loader & intermediate representation** — ✅ built
- `generator/`: load the spec, resolve `$ref`s, and build an IR per resource. Also extract spec-example fixtures, including the synthesised ones (see §1). The IR covers operations → method names, response attributes (from `collection_*`/`single_*`), request attributes (from `requestBodies`), relationships, sort/group enums, path params, content types, and binary responses. Plus an operation-naming table with overrides (`generator/overrides.php`).
- Acceptance: IR snapshot tests; every one of the 668 operations appears in the IR with a unique SDK method name.

**P-08 Emitters + drift check** — ✅ built
- Emit resource, model, input DTO, enum, factory, contract test and facade `@method` docblocks with nette/php-generator, then run Pint.
- **Regenerating the three golden resources must produce byte-identical output to P-05.** That is the acceptance test.
- Add `composer generate`, `composer generate:check`, the CI drift job, and `SpecCoverageTest` with an allowlist of all not-yet-generated tags.

### Phase 3 — Generate all endpoints (one task per domain)

Each task:
1. Remove the domain's tags from the allowlist and run the generator.
2. Add overrides for special endpoints.
3. Hand-label integer enums that matter.
4. Review the synthesised fixtures and fill gaps from the reference docs.
5. Write the domain's docs page with real examples.
6. Add (skipped) live tests.

Generated contract tests keep coverage at 100%.

| Task | Domain | Notable special cases |
|---|---|---|
| P-09 | Tasks & docs: folders/boards, task lists, task dependencies, todos, workflows, workflow statuses, discussions, comments, pages, page versions, attachments | Pages: `create_with_markdown`, `append_markdown/html`, `replace_body_*`, templates; attachments upload flow |
| P-10 | Projects & resource management: projects, project preferences, placeholders (+ usages), bookings, entitlements, events, holidays, holiday calendars, resource requests, memberships | `projects/copy`, `change_workflow`, `apply_navigation_tabs`; `resource_requests/{id}/resolve` is POST |
| P-11 | Time tracking: time entry versions, time tracking policies, timers, timesheets | Timers `stop` |
| P-12 | CRM: companies, contact entries, people, deal statuses, lost reasons, pipelines, tags, emails | `people/merge`, `deal_statuses/merge`, people invite/virtualize |
| P-13 | Financials I: deals, deal cost rates, services, service types (+ assignments), service assignments, sections, prices, rate cards, revenue distributions, contracts | `deals/create_from_origin`, `contracts/{id}/generate` (50/min limit) |
| P-14 | Financials II: expenses (+ line items, bulk), purchase orders (+ bulk), bills, bank accounts, tax rates, exchange rates, overheads, salaries (30/2min limit), proposals, document styles/types | `proposals/{id}/signed_pdf` binary; `sync_status` |
| P-15 | Invoicing: invoices, line items (+ bulk), invoice attributions, invoice templates, automatic invoicing rules, payments, payment reminder sequences, e-invoice identities | `invoices/{id}/preview`, `finalize`, `send`, `send_einvoice`, `export(_update)`; `line_items/generate` |
| P-16 | Reports & dashboards: the remaining 25 report endpoints, report categories, dashboards, widgets, pulses | Per-report group enums; page pagination only; reports throttle bucket |
| P-17 | Organization & admin: organizations, organization memberships/subscriptions, users, teams, team memberships, roles/permission sets, subsidiaries, custom fields (+ options, sections), filters, templates, skills, job roles, approval policies/assignments/statuses/workflows, time-off & survey family (surveys, fields, options, responses), notifications, activities, deleted items, custom domains, integrations (+ exporter/task-mgmt configs), webhooks + logs, agents/agent configs/agent roles, artifacts | Notification read/dismiss family; `integrations/{id}/check` and `sync` are GET actions |
| P-18 | Auth & public: sessions (incl. `machine`, `validate_otp` PUT), passwords, invitations, users `update_password`, all `public/*` uuid endpoints | No org header; possibly no token; documented as advanced/rare |

P-18 ends with an **empty allowlist** in `SpecCoverageTest`: all 668 operations are implemented and contract-tested.

### Phase 4 — Laravel integration, docs, release

**P-19 Webhooks receiver**
- First research whether Productive signs webhook payloads; the Webhooks tag docs + a live test webhook will tell.
- Then build `Route::productiveWebhooks()`, the controller, a verification middleware (secret/signature, or a shared-secret URL token if unsigned), and typed `ProductiveWebhookReceived` events with hydrated models. The queued handler is optional.

**P-20 Laravel niceties**
- `productive:ping`
- Reference-data caching helper (`->remember($ttl)` on list queries, per the rate-limit guide's advice)
- Multi-organization usage via `withOrganization()`/`withToken()` (scoped, Octane-safe)
- Queue-friendly behaviour: a `RateLimitException` exposes `retryAfter` for `$job->release()`

**P-21 Documentation pass**
- README in the langfuse style: banner, badges, quick start, features.
- `docs/`: configuration, querying & filtering, pagination, creating/updating (the `Missing` semantics), actions & bulk, reports, errors & retries, rate limiting, webhooks, testing, architecture (mermaid), troubleshooting, plus a domain page per Phase-3 task.
- Generated `docs/reference/` listing each HTTP endpoint ↔ SDK method.

**P-22 Spec drift automation & release**
- A weekly GitHub Action downloads `openapi.json`, runs the generator and opens a PR if anything changed. It can be fed straight back into Taskforce as a task.
- Tag `v0.1.0`, publish to Packagist, CHANGELOG.

**P-23 Sandbox verification** (blocked until the sandbox exists)
- Enable the live suite against the sandbox.
- Check that `links.next` starts with the exact configured base URL; otherwise page 2 of `lazy()` throws. This matters for a different host, or for `PRODUCTIVE_BASE_URL` pointing at a proxy.
- Check that `X-RateLimit-Reset` is seconds remaining, as the guide says, and not a timestamp.
- Check whether to-one relationships carry linkage `data` without `include`, which decides how often `relationshipId()` works without one.
- Check the resource types the generator could not take from the examples: `agent_roles`, `integration_exporter_configurations` and `timesheet_reports` have examples that show another resource's type. If the API really returns the example's type, `find()` throws and `query()` hydrates the other model; fix with the model map.
- Check `deal_or_budget_report` (generic `Model` for now) and the 100 other untyped relationships, and add confirmed types to `generator/config/relationship-types.php`.
- Check the bodies of `integrations`, `proposals`, `resource_requests` and `revenue_distributions` create/update (undocumented, sent from `array $data`), and the plain-JSON page body actions.
- Record real responses and swap them in for `synthesised` fixtures.
- Fix any hydration mismatches.
- Release `v0.2.0`.

---

## 5. Risks

- **Spec noise** (filter fields inside resource schemas, unlabeled int enums). Mitigated by deriving attributes from response and request schemas only, and the live smoke suite catches hydration mismatches.
- **Spec vs docs gaps** (deal contacts, KPD codes, e-invoice transactions are documented but have no paths). We implement what the spec defines and add the others by hand if they turn out to be live.
- **Generator lock-in.** If generated output is wrong, it is wrong 600 times. The P-05 golden checkpoint + byte-identical regeneration in P-08 is the control.
- **Taskforce run size.** P-17 is the largest domain (~150 ops). If a run hits max turns, split it into P-17a (org/users/roles/custom fields) and P-17b (approvals/surveys/notifications/integrations/agents).
- **Spec examples ≠ real responses.** Until P-23, we cannot prove that hydration matches production data. Mitigation: `raw` attributes, lenient hydration (unknown fields ignored, nullable by default), and P-23.
- **Rate limits during live tests.** The live suite must run through the SDK's own throttle, read-only by default, against a sandbox org.

## 6. Open questions

1. **Review of the golden pattern (P-05):** check `src/Resources/TaskResource.php`, `src/Data/Models/Task.php`, `src/Data/Input/CreateTaskData.php` and `tests/Contract/TaskResourceTest.php` before P-07 starts.
2. **Commit and push:** the code is ready but nothing is committed. Do you want one commit per P-task, or one initial commit?
