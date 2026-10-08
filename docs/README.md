# Documentation

Laravel Productive: full reference. For installation and a quick start, see the [main README](../README.md).

- [Configuration](configuration.md): credentials, multiple organizations, retries, throttling, feature flags
- [Querying](querying.md): filters, operators, logical groups, sorting, includes
- [Pagination](pagination.md): `get`, `lazy`, `all`, `first`, `count`, `paginate`, and cursor versus page numbers
- [Models and relationships](models.md): typed properties, raw attributes, included relationships
- [Creating, updating and actions](writing.md): input objects, `Undefined` versus `null`, actions, bulk operations
- [Reports](reports.md): grouping, report pagination and rate limits
- [Errors, retries and rate limits](errors.md): the exception hierarchy, retry policy and queue jobs
- [Testing](testing.md): `Productive::fake()`, fake responses, factories, assertions
- [Architecture](architecture.md): how the layers fit together
- [Development](development.md): quality gate, the vendored spec and how endpoints are added

Productive's own guides are vendored in [vendor/](vendor) for reference.
