# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- **Webhooks.** `Route::productiveWebhooks()` registers the route Productive delivers to. `VerifyProductiveSignature` checks the `Productive-Signature` header (HMAC-SHA256 over `<timestamp>.<body>` with the webhook's token, from `PRODUCTIVE_WEBHOOK_SECRETS`), `WebhookDelivery` hydrates the item with its included resources, and `ProductiveWebhookReceived` is dispatched. `WebhookEvent` lists the 35 events with their ids, labels and delivery names. `FakeWebhook` builds payloads, signed requests and events for tests. The test Productive sends from its webhook settings ("test_create_task", without an item) is answered with 200 and dispatched with `$delivery->test` set. The payload format comes from real deliveries; `composer webhook:capture` records them and `composer webhook:fixtures` turns them into fixtures. See [Webhooks](docs/webhooks.md).
- **An API coverage page.** `docs/coverage.md` gives every operation a live status: recorded, exercised, empty list, rejected, plan-gated, skipped, not recorded or not exercised. `generator/config/coverage.php` holds the reasons for what the test organization's plan lacks and what is left out on purpose.
- **Example-only attributes never seen are listed.** Productive's spec examples name 223 attributes on 52 models that its schemas do not and that no recorded response carried (`revenue`, `cost` and `profit` on the reports, `url`, `events` and `active` on webhooks). Their properties stay, always null so far, and [API differences](docs/api-differences.md) lists them until Productive confirms whether they exist.
- **The recorder reaches further.** It calls the GET actions that only read (an invoice's preview, a proposal's sync status and signed PDF), fetches resources without an index by an ID another response carried (bills, the organization's subscription), and a publicly shared page by the uuid in `RECORDING_PUBLIC_PAGE_UUID`. A binary body is noted instead of kept.
- **`composer record:writes` exercises the write side.** It runs the recipes in `generator/config/recipes.php` against the test organization: a create, an update and the actions of each resource, then a cleanup (destroy, archive, or several in order). Without `--execute` it prints the plan. Resources that leave Productive or change the account, and actions that send something out, are refused. A recipe named `<path>#<label>` makes a second record, by a create or by `create_with` another operation such as a copy. `composer record:types` reads the write runs too: their successes mark operations exercised on the coverage page, and the attributes a create answered "can't be blank" for become required in its input. Every write of tier 1 (settings) is exercised or carries a reason.

### Changed

- **More create inputs require what the API demands.** Learned from `composer record:writes`: `CreateDocumentStyleData` requires `styles`, `CreateDocumentTypeData` requires `locale`, `CreateRoleData` requires `base_role_id` and `CreateWidgetData` requires `column_position` and `row_position`. Code that left them out failed at the API with a 422 and now fails when the input is built.

## [0.1.0] - 2026-10-12

The first release: a typed, tested Laravel SDK for the whole Productive API.

### Added

- **Every endpoint.** 668 operations of the OpenAPI spec on 139 resources, plus the 3 single creates the spec hides behind its bulk variants. Generated from the spec into typed resources, models, inputs, enums, factories and contract tests. See the [endpoint reference](docs/reference.md).
- **Checked against the live API.** Models follow real responses where the spec's types are wrong: 31 corrected types, 73 undocumented attributes and 10 undocumented relationships. Lists sent as JSON text (a person's availabilities) are decoded. Create inputs require the fields the API demands but the spec does not mark. See [API differences](docs/api-differences.md).
- **Core.**
  - Configuration, and a connector with typed exceptions that never follows redirects.
  - A retry policy: 429 for every method; server errors and dropped connections for reads only.
  - Cache-backed client-side throttling, per token, with the reports limit.
  - Multiple organizations and tokens per call, Octane-safe.
- **JSON:API.** Documents, resource objects, relationships, an identity index for included resources, and request builders including `ext=bulk`.
- **Querying.** Filters with operators and nested `whereAny`/`whereAll` groups, sorting, includes and report grouping. `get`, `lazy`, `all`, `first`, `count` and `paginate`, with cursor pagination and a fallback to page numbers. A repeated next link stops instead of looping.
- **Models and inputs.** Readonly models with typed properties and relationship accessors that throw on a type mismatch. Inputs distinguish omitted fields from explicit nulls.
- **Testing.**
  - `Productive::fake()` records requests as they go over the wire and seeds responses by operation or path.
  - Assertions, including `assertSentToOrganization()`.
  - Factories built from Productive's examples.
- **Laravel.** `php artisan productive:ping` and a publishable config.
- **Maintenance.**
  - `composer record` (GET only, guarded to one organization), `composer seed`, `record:types` and `record:fixtures` with a leak check.
  - A weekly spec-drift workflow that opens a pull request when the generated SDK changes.

### Known limitations

- Reads are verified against recorded responses. Of the writes, only 14 creates have been sent to the live API; updates, deletes and actions are checked against the spec and the testing fake.
- No webhook receiver yet.

[Unreleased]: https://github.com/axyr/laravel-productive/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/axyr/laravel-productive/releases/tag/v0.1.0
