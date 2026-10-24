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
    3. Recorded types in the generator. **Built (2026-10-10), first recording of 2026-10-09:**
       - The generator reads only `generator/recorded-types.json` (value kinds, no values). Scrubbed fixtures exist only for `RecordedHydrationTest`.
       - Result: 30 type overrides, 73 undocumented attributes, 2 undocumented relationships. `boards` is removed, and `board` is gone from task lists and task reports. On the old models the hydration test fails for 38 of 72 types.
       - Checked before becoming a rule, and left alone: the missing X-RateLimit headers (only 200s were seen; the retry logic is unchanged), money staying int where the spec says integer (number fields keep the float reader), and the 403s on agents and skills (listed as not checked).
       - Merged as #13. Follow-up PR after the user seeds the 58 empty resources and records again: run `record:types`, `record:fixtures` and `generate`. The review is the diff of `docs/api-differences.md` plus the leak search.
       - Follow-up (2026-10-11):
         - The trial was seeded with `composer seed`: 14 records created through the SDK.
         - Then two more recordings: 381 calls each.
         - `Person.availabilities` is JSON text in all 92 recorded people. It is now the `json` type, read by `Attributes::json()`.
         - Recorded relationships without data are included in the next run. Result: 10 undocumented relationships typed (`Task.subtasks`, `Task.todos`, `Invoice.invoiced_budgets`, …), and 9 still without data.
         - Report calls are paced at one per 4 seconds, after two runs stopped on a 429. Fixed windows of 3 seconds still allow 11 calls in 30 seconds.
       - Write side, found by seeding:
         - Required fields the spec does not mark:
           - bank accounts: `number`;
           - templates: `model_attributes` with project type, manager and workflow;
           - proposals: `document_type_id` and `status`;
           - invoice templates: `budget_id` and `document_type_id`;
           - resource requests: `title`, `currency`, `service_id`, `booking_method_id` and `time`.
         - `budget_id` must point at a budget, not a deal.
         - Not available in the trial: teams, rate cards, deal cost rates, purchase orders and timesheets (plan or feature limits). Comments, task dependencies and task updates are refused for the token (403).
         - Placeholders reject the project ("project attribute is invalid"); the cause is unknown.
         - These belong in input validation (step 4).
       - Rules: `null` and empty lists never override the spec; int vs float widens to float; an override needs a non-null value that the lenient reader cannot read.
       - `docs/api-differences.md` lists every override.
       - Tests hydrate every recorded resource and check each value, and every recorded relationship must have an accessor. This fixes the 23 conflicts and the 29 missing relationships.
    4. Strict request validation: a `data` wrapper and the right `type` are required, unknown keys fail, every input class is validated in full, and the response kind and organization header are recomputed from the raw spec.
       - **Done (2026-10-12):**
         - `RequestSchema` requires a JSON:API body's `data` with the resource's own type, and rejects attributes the spec does not list. The generated contract tests pass the type. The helpers have negative tests.
         - Every input class is built with every attribute and validated against its schema: 210 classes. Two generator gaps found:
           - `Samples` gave `'Example'` to untyped `*_ids`/`tag_list` inputs, which take arrays.
           - List items with allowed values were not recorded; the IR now has `itemEnum`.
         - Response kind and organization header are recomputed from the raw spec in `SpecConsistencyTest`. The 9 deliberate deviations are listed with their reasons.
         - `generator/config/required.php` adds the create fields the live API requires but the spec does not mark: bank account `number`, invoice template `budget_id`/`document_type_id`, template `model_attributes`. Proposals and resource requests take `array $data` (no input class), so their required fields stay documented in `generator/bin/seed`.
         - Time attributes: the spec says `format: time` (RFC 3339) but describes "HH:MM"; the SDK sends HH:MM.
    5. Fake fixes. **Done (2026-10-12):** bodies recorded as JSON, `assertSentToOrganization()` via the new `ConfigurableConnector` contract, `respond()` overrides earlier seeds, path patterns match absolute next links, public defaults typed, factories keep a real `type` attribute and `count(0)` gives none, the fake's deliberate differences documented. The items as found by the audit:
       - The seeded paging loop, public page and artifact defaults, and JSON-encoding recorded bodies.
       - Seed precedence, recording which organization a request went to, and the vacuous rate-limit test.
    6. Honest generator tests:
       - A mutation run without the drift group, with survivors pinned by unit tests. **Emitters done (2026-10-11, #15):**
         - Every emitter has tests stating its exact output for cases covering its branches, each checked by hand.
         - Mutation testing of `generator/src/Emit` without the drift group went from 65% (747 untested, 81 min) to 100% (52 min).
         - The tests found two flaws: a model shared by two resources linked to the last resource's reference, and the group classes lacked a blank line.
         - A full local run of all 117 hand-written files took more than 6 hours: the generator's IR builder files are slow because their mutants re-run the full-API tests. So the nightly now runs two parallel jobs, core (67 files) and generator (50). The IR builder files get fast unit tests like the emitters did.
         - Decided 2026-10-12: the core job (`src/`, 71 min, 100%) blocks; the generator job only reports. The real risks were spec mismatches, which the recordings and contract tests catch, not weak generator tests. `FullApiTest` is in the `full-api` group and left out of mutation runs.
         - The generator job did not fit 240 minutes even without `full-api` (other slow tests cover generator code), so it was dropped from the nightly: the nightly is core only, 56 minutes on CI, 100%, and `MUTATION_GATE` is back to blocking (2026-10-12).
       - Tests for the test helpers, and honest names for the "golden" tests.
    7. Mutation scope, **decided 2026-10-09: hand-written code only.**
       - The baseline nightly before Phase 3 took 2 h 6 min for 4,034 mutants. With all generated code the run would need about 12 hours of runner time every night.
       - `generator/bin/mutate` (`composer test:mutate`, and the nightly) mutates the 108 files without the generated marker: the package core and the generator, emitters included. A fault in generated code comes from an emitter.
       - The caveat from the audit: the contract tests check requests in full, but responses only by class. Generated models get value tests in step 3. Until then, generated code has neither value tests nor mutants.
       - `MUTATION_GATE` in ci.yml goes back to "blocking" once a manual run of the scoped workflow on main is green.
       - The first scoped run timed out after 5 of 108 files. Every emitter mutant re-ran `DriftTest`, which regenerates and formats all 883 files (15 s). Without the `drift` group, `ContractTestEmitter` takes 10.5 min instead of 94, and scores 45%: 145 of its 264 mutants were only killed by "the output changed". The run now excludes `drift`. Emitter unit tests (step 6, starting with `ContractTestEmitter`) bring it to 100% before the gate goes back to blocking.
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
- Vendor the spec to `resources/openapi/productive.json`. The guides are linked, not copied: see `docs/README.md`.
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

**P-20 Laravel niceties** — **done (2026-10-12):** `php artisan productive:ping`; caching and queued-job release documented in `docs/configuration.md` and `docs/errors.md`. A built-in `->remember()` was left out: caching hydrated models inside the SDK is fragile across upgrades, and `Cache::remember()` around a query does the same.
- `productive:ping`
- Reference-data caching helper (`->remember($ttl)` on list queries, per the rate-limit guide's advice)
- Multi-organization usage via `withOrganization()`/`withToken()` (scoped, Octane-safe)
- Queue-friendly behaviour: a `RateLimitException` exposes `retryAfter` for `$job->release()`

**P-21 Documentation pass**
- README in the langfuse style: banner, badges, quick start, features.
- `docs/`: configuration, querying & filtering, pagination, creating/updating (the `Missing` semantics), actions & bulk, reports, errors & retries, rate limiting, webhooks, testing, architecture (mermaid), troubleshooting, plus a domain page per Phase-3 task.
- Generated `docs/reference.md` listing each HTTP endpoint ↔ SDK method ↔ operation key — **done (2026-10-12)**, drift-checked.

**P-22 Spec drift automation & release** — the weekly workflow is done (2026-10-12): it opens a PR only when the generated SDK changes, because Productive's export reorders parameters between downloads. Release still to do.
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

### Phase 5 — Live completeness: every endpoint recorded or exercised, webhooks, and a report for Productive

**Starting point (2026-10-23).** The test organization now has webhooks, which Productive only offers on the Ultimate plan, so the plan-gated features that stopped `composer seed` on 2026-10-11 (teams, rate cards, overheads, recurring budgets, approvals) should be available too.

| Spec operations | Count | Live status |
|---|---|---|
| GET (index, show, actions) | 247 | 188 recorded with a 200 response; 59 never answered 200: shows of empty lists, three GET actions, `agents`/`agent_configs`/`skills` (403), `boards` (404), and `sessions`/`public/*` which the recorder skips on purpose |
| POST, PATCH, PUT, DELETE | 421 | Never exercised live, except the creates in `composer seed` |
| Resources whose list was empty | 52 | Listed under "Not recorded yet" in `docs/api-differences.md`; their models still use the spec's types |

Why `composer seed` could not fill the empty lists, from `recordings/seed-2026-10-11-*`:

| Cause | Resources |
|---|---|
| Feature switched off in settings | `timesheets` ("Timesheet submission feature disabled"), `purchase_orders` ("purchase order setting is turned off"), `deal_cost_rates` ("feature unavailable") |
| Trial plan limit | `teams` ("Plan limit for teams exceeded"), `rate_cards` ("Rate card count reached") |
| 403 for the owner's token | `comments`, `task_dependencies` (to check in the UI: a permission, a feature gate, or a bug to report) |
| Payload the spec does not explain | `placeholders` ("project attribute is invalid") |
| Only the UI (or a person) can make them | `tags`, `timers`, `attachments`, `pages`, `page_versions`, `discussions`, `notifications`, `invitations`, `deleted_items`, `survey_fields`, `survey_field_options`, `survey_responses`, `contracts`, `overheads`, `approval_workflows`, `approval_policy_assignments`, `approval_statuses`, `webhook_logs` |
| Plan-gated or out of scope | `agents`, `agent_configs`, `agent_roles`, `skills` ("Agents and Connectors" are not in Ultimate), `custom_domains`, `einvoice_identities`, `integrations` and their configurations, `emails`, `sessions`, `passwords` |

Every step below says who does it (**You** in Productive, **SDK** here), ends with a check, and is one pull request at most. Every SDK step that touches the live API finishes with the same chain: `composer record` (about 12 minutes) → `composer record:types` → `composer record:fixtures` → `composer generate` → `composer quality` → review `docs/api-differences.md` → PR. Order: 1 → 2 → 3 → 4 → 5 → 6 → 7. Step 4 comes before step 5 so the write runs also deliver real webhooks to the capture server.

**Step 1 — Baseline and a scoreboard (SDK, half a day, no UI work)** — **done (2026-10-23)**, branch `feature/coverage-ledger`. The third recording found: `agents`, `agent_configs` and `skills` answer 200 now (empty lists, so they are step 2 items, not plan-gated); `invoices/{id}/preview` answers 422 asking for `budget_ids` and more on a GET (report item); `proposals/{id}/signed_pdf` answers 404 for an unsigned proposal; `webhook_logs` is empty because only failed deliveries are logged. The spec's examples name 223 attributes on 52 models that the schemas do not and no response ever carried (`revenue`, `cost`, `project_id` on reports; `url`, `events` on webhooks); they are kept, always null, and listed as never seen (the user chose 2026-10-23 not to drop them on a recording's absence alone; report item, ask Productive). The ledger: 194 recorded, 35 empty lists, 4 not recorded (show-only resources whose ID no response carried yet: `agent_roles`, `approval_workflows`, `bills`, `invitations`), 400 writes not exercised.
1. Add a coverage ledger, `docs/coverage.md`, generated like `api-differences.md` from `generator/recorded-types.json` and the spec: one row per operation with its live status (recorded 200, empty list, rejected, plan-gated, skipped on purpose, exercised by a write run, never exercised). Plan-gated and skipped rows carry their reason from a small config file. This is the definition of done for the phase: no row may say "never exercised" without a reason.
2. Teach the recorder the safe GET actions it skips today: `invoices/{id}/preview`, `proposals/{id}/sync_status`, `proposals/{id}/signed_pdf` (an allowlist; `integrations/{id}/check` and `sync` stay out because they act), and shows reachable only through a relationship (`organization_subscriptions/{id}` from the organization). Also one public page by uuid when `RECORDING_PUBLIC_PAGE_UUID` is set (step 2.12 creates it).
3. Run the chain. Expect the webhooks list to be recorded for the first time and the `Webhook` model to lose its phantom attributes: the spec's example uses `url`, `events`, `active` and `secret`, its schema uses `target_url`, `event_id`, `type_id` and `signature_token`, so the generated model has both (first entry for the report, step 6).
Check: `docs/coverage.md` exists, every row has a status, the third recording is committed as types and fixtures.

**Step 2 — Your checklist in Productive (You, about 90 minutes in one sitting)** — **done (2026-10-23)**. Nothing said "upgrade". Missing: tags (no tag field on tasks; Productive phases tags out for new organizations in favour of custom fields). Findings: the 403s on `comments` and `task_dependencies` came from the owner not being a project member, not from the plan; deal cost rates have no organization switch, they are set per budget or deal ("Set custom cost rates" in its sidebar); approval policies live under Settings → Deal, and a budget only offers a custom policy; the UI's resourcing placeholder is a `people` record with `person_type` 3, while the API's `placeholders` are slots on a template project; deleting a holiday is permanent, so it never reaches `deleted_items`; `overheads` stays empty until the monthly calculation; `page_versions` did not grow from two edits. Webhooks: nine "SDK: … created" webhooks deliver to `composer webhook:capture` through a quick tunnel, the signature token is in `.env` as `PRODUCTIVE_WEBHOOK_SECRET`, and test and real deliveries verify (hex). A quick tunnel dies when the Mac sleeps (the URL changes), so keep it awake with `caffeinate -s` or use the ingress on the taskforce server.
Each item names what it unlocks. Settings first, then one project. When an item says "upgrade" or does not exist, note it: it becomes a plan-gated row in the ledger instead of a to-do.

Settings:
1. Settings → Time tracking → Timesheets: switch on timesheet submission → `timesheets`.
2. Settings → Approvals: switch on time and expense approvals, add one approval policy with a workflow and assign it to yourself → `approval_workflows`, `approval_policy_assignments`; `approval_statuses` appears after step 18.
3. Settings → Purchase orders: switch the setting on → `purchase_orders`, `bills`.
4. Settings → Budgets (financials): switch on cost rates per budget/deal → `deal_cost_rates`.
5. Settings → Teams: if the page exists, do nothing; `composer seed` creates "Seed team" in step 3. If it still says "upgrade", note it → `teams`, `team_memberships`.
6. Settings → Rate cards: if the page lets you add one, do nothing; seed creates it. Otherwise note the limit → `rate_cards`, `prices`.
7. Settings → Overheads: add one overhead with any amount → `overheads`.
8. Settings → Webhooks: leave as they are for now; step 4 gives you the target URL.
9. Settings → People: invite a second person on a plus-alias of your own address (`info+sdk@…`), accept the invite in a private window, keep the person active → `invitations`, and a second person for memberships, notifications and task assignment. This sends one email, to you.
10. Settings → Tags (or the tag picker on any task): create a tag `sdk` → `tags`.

One project (use an existing seeded project):
11. Docs: create a page "SDK page", save it twice with a change (`pages`, `page_versions`), select some text and leave a comment on it (`discussions`).
12. Share that page publicly and paste the public link's uuid into `.env` as `RECORDING_PUBLIC_PAGE_UUID` → `public/pages`.
13. Open a task: write a comment that mentions the second person and one that mentions you (`comments`, `notifications`), upload a small file (`attachments`), start a timer and leave it running (`timers`), add a dependency on another task (`task_dependencies`; if the UI allows what the API refused with 403, that goes in the report), add a subtask.
14. Resourcing: add a placeholder and book it on the project (`placeholders`, `placeholder_usages`). Note what the form required; the API said "project attribute is invalid".
15. Budgets: add a recurring budget to a deal → `contracts`.
16. Forms/surveys: open "Seed survey", add one field with two options, submit one response → `survey_fields`, `survey_field_options`, `survey_responses`.
17. Delete "Seed holiday" (Settings → Holidays) → `deleted_items`.
18. Time: log an entry, submit the timesheet, approve the entry as the approver → `approval_statuses`, `timesheets`.
19. Reports: save any report in a new category → `report_categories`.
20. AI: the API lists `agents`, `agent_configs` and `skills` for this organization (empty). Create one agent and one skill if the UI offers them; note it if it says "upgrade". Try once, then note the result: anything named Artifacts, Pulses or Meeting notes.

Leave alone on purpose: sessions, passwords, custom domains, e-invoice identities, integrations, emails, the subscription.
Check: send me the list of items that worked and the ones that said "upgrade" or were missing.

**Step 3 — Seed and record again (SDK, one hour)** — **done (2026-10-23)**, branch `feature/seed-and-record`. The seed created teams, team memberships, the holiday, rate cards, prices, deal cost rates, purchase orders (they need a `vendor_id`, which the spec does not require), placeholders and placeholder usages (on the template project and its task, which the plain lists only return with `filter[template]=true`). The fourth recording (`2026-10-23-224821`) took "Not recorded yet" from 50 resources to 16. Not met: the check expected only plan-gated and skipped resources; `agent_roles`, `bills`, `deleted_items`, `overheads`, `pulses`, `reports/automation_reports` and `tags` are still empty. A recording run fails on the first connection timeout; two runs died when the Mac slept, so run it under `caffeinate -s`.
1. `composer seed` dry run, then `--execute`: teams, rate cards, deal cost rates, purchase orders, placeholders (with what step 2.14 taught) and timesheets should pass now; comments and task dependencies are retried to see whether the 403 was the plan.
2. Run the chain.
Check: the "Not recorded yet" table in `api-differences.md` only lists plan-gated and skipped resources; the 50-odd missing shows are recorded; `RecordedHydrationTest` passes on every new fixture.

**Step 4 — Webhooks: capture real deliveries, then ship a receiver (SDK one day, You 30 minutes)** — **receiver done (2026-10-23)**, branch `feature/webhook-capture`. The test organization's eight webhooks (deal, person and company events) deliver to work-os develop through its Cloudflare Tunnel, and work-os stores every call with its raw body in `webhook_calls` on the `taskforce` server; four real deliveries were exported from there into `recordings/webhooks-2026-10-23-work-os/` and became the first fixtures. The envelope is `{event, item_type, item_id, created, object: {data, included}}`; the signature scheme is confirmed in hex. Still open: deliveries of the other event families (tasks, time entries, invoices, payments, expenses, bookings, budgets, projects, contacts), which need a webhook target that reaches `composer webhook:capture`: `cloudflared tunnel --url http://127.0.0.1:8787` on this machine, or a cloudflared ingress on the taskforce server to this machine's tailnet address; and a `webhook_logs` record (point one webhook at a URL that answers 500).
What Productive documents (help article "Using webhooks in Productive", reference page `resources/webhooks`): Settings → Webhooks → Add Webhook, one event per webhook ("Fire After Event"), 35 events with labels on the reference page (1 New task … 35 Update contact), a `Productive-Signature` header `t=<seconds>,s=<hex>` where `s` is HMAC-SHA256 of `<t>.<raw body>` with the signature token shown in the webhook logs, custom headers set from the test menu, a "send test" button, retries on any non-2xx (the article says 12 attempts over 48 hours and every 12 hours, which contradict each other; report), deactivation after 12 failures, logs for failures and tests only, 1,000 events per 5 minutes. The payload format is not documented anywhere, which is why we capture it.
1. (SDK) `composer webhook:capture`: PHP's built-in server on a local port that writes every request (headers and raw body, redacted like the recorder) to `recordings/webhooks-<date-time>/`. You expose it with a tunnel (`ngrok http 8787`, or Expose) so no third-party request bin ever sees organization data.
2. (You) Settings → Webhooks: create one webhook per event family (task, time entry, deal, budget, project, company, person, contact, invoice, payment, expense, booking) with the tunnel URL as Target URL, press "send test" on each, add one custom header on one of them, copy the signature token from the logs into `.env` as `PRODUCTIVE_WEBHOOK_SECRET`. Then create, change and delete one task by hand. Point one extra webhook at a URL that answers 500 so `webhook_logs` gets an entry; delete it afterwards. Step 5 produces the other real events.
3. (SDK) From the captures: scrubbed fixtures under `tests/Fixtures/Webhooks`; enums `WebhookEvent` (35 labels), `WebhookType`, `WebhookState`; a `VerifyProductiveSignature` middleware (timing-safe compare, timestamp tolerance, raw body); a `Route::productiveWebhooks()` macro with a controller that dispatches a `ProductiveWebhookReceived` Laravel event carrying the event, the hydrated model through `ModelMap` and the raw payload; `Productive::fakeWebhook()` for user tests; `docs/webhooks.md`. Record `webhooks.show` and `webhook_logs.index` through the chain.
Check: a test delivery and a real task event pass the middleware in a Laravel app; the fixture-based tests cover every captured event; the retry schedule seen in the logs is noted for the report.

**Step 5 — Exercise the write side (SDK three to four days in five PRs, You ten minutes per PR)**
1. `composer record:writes`, grown from `generator/bin/seed`: per-resource recipes in `generator/config/recipes.php` (a create payload, one harmless update, each action with its arguments, and cleanup), dry run by default, `--resource=tasks` to run one at a time, `--execute`, the same guards as the recorder (organization check, one request per second, no redirects, stop on 429). Every record it creates is named "SDK check …"; it deletes what it created in reverse order, also after a failure (`--keep` to inspect). It records request and response per operation like the recorder, redacted, and `record:types` learns from writes too: attributes the API requires (422 "can't be blank" lands in `required.php` automatically), attributes it refuses, whether a delete answers 204 or a body, what actions return. The ledger marks each operation as exercised.
2. Never in a recipe: anything that leaves Productive or changes the account: sending invoices, proposals or emails, invitations (step 2.9 was the one), integrations, custom domains, e-invoicing, subscription, users, organization updates, sessions, passwords. Deletes only of records the run created.
3. Five tiers, one PR each. Per tier: dry run → you read the payload list (two minutes) → execute (five to ten minutes live) → `record:types`, `generate`, fix generator rules → PR.
   - T1 settings, harmless: tags, job roles, holiday calendars and holidays, tax rates, lost reasons, deal statuses, pipelines, workflows and statuses, custom fields, options and sections, document styles and types, filters, dashboards and widgets, teams and memberships, time tracking policies, service types, roles, report categories, templates.
   - T2 CRM: companies, contact entries, people as contacts (no invite), deals and budgets, deal cost rates, rate cards and prices, proposals and their actions, subsidiaries.
   - T3 work: projects (copy, archive), folders, task lists, tasks (reposition, copy, move dependent, bulk), todos, comments, task dependencies, sections, services, service assignments, bookings (bulk), resource requests, timers (start, stop), time entries (bulk, approve, unapprove), timesheets, entitlements, events, salaries.
   - T4 finance: expenses (bulk), expense line items, invoices with line items (bulk; finalize only a test invoice, never send), payments, invoice attributions, invoice templates, automatic invoicing rules, payment reminder sequences, purchase orders, bank accounts, overheads, revenue distributions, bills.
   - T5 docs and the rest: pages and the four body actions, discussions, surveys, fields, options and responses, attachments (the upload flow), webhooks (create, test, delete), notifications (mark read), memberships, organization memberships, placeholders, approval policies.
4. `composer record:writes --probe-rate-limit`: eleven report requests in a burst, records the headers of the 429 only, so the `X-RateLimit-Reset` format is finally known and `RetryPolicy` can be checked against it.
Check per tier: every operation in the tier is marked exercised or carries a reason; the generated inputs require what the API requires; the contract tests still pass; the write-response fixtures hydrate.

**Step 6 — The report for Productive (SDK, half a day)**
1. `docs/spec-report.md`, generated by a new emitter from the same sources as `api-differences.md` plus `required.php`, `removed.php`, the recipes' notes, and `generator/config/spec-findings.php` for what no recording can express. Every entry: endpoint, what the spec says, what the API does, how to reproduce (date, request). Sections:
   - types that differ, undocumented attributes and relationships, rejected includes, removed resources (`boards`), undocumented resources the Ruby client has;
   - attributes required by the API but not marked (`bank_accounts.number`, `invoice_templates.budget_id`/`document_type_id`, `templates.model_attributes`, `proposals.document_type_id`/`status`, `resource_requests` title/currency/service/booking_method_id/time, and what step 5 adds);
   - operations documented without a body: `proposals`, `resource_requests`, `integrations`, `revenue_distributions`;
   - examples that contradict their schemas: the webhook example (`url`/`events`/`active`/`secret`), examples copied from another resource (`agent_roles`, `integration_exporter_configurations`, `timesheet_reports`), filter examples such as `{"eq": "value"}` on attributes;
   - structural: bulk operations hide their single twins, create and update share one `required` list, filter-only fields inside resource schemas, integer enums without labels in the JSON, relationship targets missing, parameter order changing between downloads;
   - behaviour: feature gates answering 422 in one place and 403 or 409 in another, `comments` and `task_dependencies` POST 403 for an owner token (if step 3 confirms it), the retry schedule contradiction, and whatever step 5 finds.
2. Send Productive the rendered page (the GitHub URL, or a PDF export) and keep regenerating it, so later recordings can be sent as an update.
Check: every entry has a reproduction; nothing in it names the organization, people or tokens.

**Step 7 — Close out (SDK, half a day)**
1. `docs/coverage.md` has no "never exercised" row without a reason; `docs/development.md` documents `record:writes`, `webhook:capture` and the ledger; `docs/testing.md` documents `fakeWebhook()`.
2. CHANGELOG under Unreleased, release 0.2.0.
3. A monthly reminder to re-record (it needs the token, so it stays manual); the weekly spec drift workflow stays.

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
