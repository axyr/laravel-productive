# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Core: configuration, HTTP connector with typed exceptions, retry policy (429 for every method; 5xx and connection failures for reads only) and cache-backed client-side throttling.
- JSON:API layer: documents, resource objects, relationships, an identity index for included resources and request document builders (including `ext=bulk`).
- Query builder: filters with operators and nested `whereAny` / `whereAll` groups, sorting, includes, report grouping, page-number and cursor pagination.
- `PendingQuery` with `get`, `lazy`, `all`, `first`, `count` and `paginate`, using cursor pagination with automatic fallback to page numbers.
- Readonly models with typed properties and relationship accessors; input objects that distinguish omitted fields from explicit nulls.
- Reference resources: tasks, time entries (including bulk operations) and the time report.
- Testing: `Productive::fake()`, `FakeResponse`, request assertions and model factories.
