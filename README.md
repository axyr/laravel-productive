# Laravel Productive

[![CI](https://github.com/axyr/laravel-productive/actions/workflows/ci.yml/badge.svg)](https://github.com/axyr/laravel-productive/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat)](#quality)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg?style=flat&logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-FF2D20.svg?style=flat&logo=laravel)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[Productive](https://productive.io) is an agency management platform: projects, tasks, time tracking, budgets, invoicing and resource planning in one place.

This package is a typed, tested Laravel SDK for the [Productive API](https://developer.productive.io). It handles authentication, JSON:API parsing, filtering, pagination, rate limits and retries, so your code reads like Laravel:

```php
use Axyr\Productive\ProductiveFacade as Productive;

$tasks = Productive::tasks()->query()
    ->where('project_id', 31002)
    ->where('due_date', '>=', '2026-10-01')
    ->include('assignee')
    ->sort(TaskSort::DueDateDesc)
    ->lazy();                      // follows every page while you iterate

foreach ($tasks as $task) {
    echo $task->title . ' → ' . $task->assignee()?->attribute('first_name');
}

$task = Productive::tasks()->create(new CreateTaskData(
    title: 'Draft Q3 launch plan',
    projectId: 31002,
    taskListId: 7781,
    dueDate: now()->addWeek(),
));

Productive::tasks()->update($task->id, new UpdateTaskData(dueDate: null)); // explicit null clears; omitted fields stay untouched
```

## Features

- **Typed models and inputs.** Readonly models with typed properties and relationship accessors, and input objects with named arguments. Raw attributes stay available, so fields this SDK does not know yet are never lost.
- **Laravel-style querying.** `where`, operators (`>=`, `Operator::Contains`), nested `whereAny` / `whereAll` groups, `sort`, `include`, `group`, plus `get`, `lazy`, `all`, `first`, `count` and `paginate`.
- **Pagination.** Cursor pagination by default. It falls back to page numbers for reports and for sorts the cursor cannot follow, and `paginate()` returns a Laravel `LengthAwarePaginator`.
- **Fails loudly.** Every error status throws a typed exception (`ValidationException`, `NotFoundException`, `RateLimitException`, …) with the API's error details.
- **Rate limits and retries.**
  - Client-side throttling keeps you within Productive's limits, shared across queue workers through the cache.
  - A 429 is retried after the reset window.
  - Reads are retried after server errors.
  - Writes are never retried after a server error, because they may already have been applied.
- **Multiple organizations.** `Productive::withOrganization($id)` and `withToken($token)` switch per call.
- **Testing.** `Productive::fake()` records requests, returns seeded or sensible default responses, and provides assertions such as `assertCreated()`. Factories build realistic models from Productive's own examples.
- **Production-ready.** Octane-safe scoped bindings, never sends credentials to another host, 100% line and type coverage, PHPStan at max level.

## Installation

Requires PHP 8.3+ and Laravel 12 or 13.

```bash
composer require axyr/laravel-productive
```

Add your credentials to `.env`. Create a token in Productive under **Settings → API integrations**; the organization ID is shown on the same page.

```dotenv
PRODUCTIVE_API_TOKEN=your-token
PRODUCTIVE_ORGANIZATION_ID=12345
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=productive-config
```

## Usage at a glance

```php
// Read
$task  = Productive::tasks()->find(120501, include: ['project']);
$page  = Productive::tasks()->query()->where('project_id', 1)->paginate(perPage: 50);
$count = Productive::timeEntries()->query()->where('person_id', 12)->count();

// Actions and bulk
Productive::tasks()->reposition($task->id, new RepositionTaskData(moveAfterId: 555));
Productive::timeEntries()->approve(84848214);
Productive::timeEntries()->bulkCreate([$entryA, $entryB]);

// Reports
Productive::reports()->timeReports()->query()
    ->group(TimeReportGroup::Person)
    ->where('after', '2026-09-01')
    ->all();

// Errors
try {
    Productive::tasks()->create(['project_id' => 1]);
} catch (ValidationException $e) {
    $e->first('title'); // "can't be blank"
}
```

## Documentation

Full reference in [docs/](docs/README.md):

- [Configuration](docs/configuration.md): credentials, multiple organizations, retries, throttling, feature flags
- [Querying](docs/querying.md): filters, operators, logical groups, sorting, includes
- [Pagination](docs/pagination.md): `get`, `lazy`, `all`, `first`, `count`, `paginate`, and cursor versus page numbers
- [Models and relationships](docs/models.md): typed properties, raw attributes, included relationships
- [Creating, updating and actions](docs/writing.md): input objects, `Undefined` versus `null`, actions, bulk operations
- [Reports](docs/reports.md): grouping, report pagination and rate limits
- [Errors, retries and rate limits](docs/errors.md): the exception hierarchy, retry policy and queue jobs
- [Testing](docs/testing.md): `Productive::fake()`, fake responses, factories, assertions
- [Architecture](docs/architecture.md): how the layers fit together
- [Development](docs/development.md): quality gate, the vendored spec and how endpoints are added

## Endpoint coverage

The SDK is built against Productive's OpenAPI spec, vendored at `resources/openapi/productive.json`: 668 operations across 117 resource groups. Every resource is generated from that spec by `composer generate`:

- **work management:** projects, tasks, task lists, folders/boards, task dependencies, to-dos, workflows and statuses, pages and docs, discussions, comments, attachments
- **resource planning:** bookings, events, holidays, entitlements, resource requests, placeholders, memberships
- **time tracking:** time entries (with bulk operations), timers, timesheets, time tracking policies
- **CRM:** companies, people, contact entries, pipelines, deal statuses, lost reasons, emails, tags
- **financials:** deals and budgets, services and service types, prices, rate cards, sections, contracts, expenses (with bulk operations), purchase orders, bills, salaries, overheads, revenue distributions, proposals, document types and styles, tax rates, bank accounts, exchange rates
- **invoicing:** invoices, line items (with bulk operations), invoice attributions and templates, automatic invoicing rules, payments, payment reminder sequences, e-invoice identities
- **reports and dashboards:** all 26 reports, report categories, dashboards, widgets, pulses
- **organization and administration:** organizations, memberships and subscriptions, users, teams, roles, subsidiaries, custom fields, filters, templates, skills, job roles, approvals, surveys, notifications, activities, deleted items, custom domains, integrations, webhooks, agents, artifacts
- **authentication:** sessions, passwords, invitations
- **public links:** shared pages, artifacts and proposals by uuid (`Productive::public()->pages()->find($uuid)`), sent without the organization header

How the code is generated is described in [Development](docs/development.md).

> [!IMPORTANT]
> **Not yet verified against the live API.** Every method is generated from Productive's OpenAPI spec and tested against the spec's own examples and schemas, but no request has been sent to Productive yet. Only the tasks, time entries and time report resources were checked by hand against the documentation. Where the spec is wrong or incomplete, the SDK will be too: please open an issue if a call behaves differently from what is documented here.

## Quality

Every change must pass `composer quality`:

- Pint
- PHPStan at max level
- PHPMD
- Pest with **100% line coverage** and **100% type coverage**

Mutation testing of the hand-written code and the generator (`composer test:mutate`, 100% gate) runs nightly in CI. Request bodies in the contract tests are validated against the spec's JSON Schemas, and fixtures are Productive's own examples.

## License

MIT
