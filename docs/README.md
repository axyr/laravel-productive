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
- [Development](development.md): quality gate, the vendored spec, recording real responses and how endpoints are added
- [Endpoint reference](reference.md): every endpoint with the SDK method that calls it (generated)
- [API differences](api-differences.md): where the live API differs from Productive's spec (generated)
- [API coverage](coverage.md): how far every endpoint has been checked against the live API (generated)

## Productive's own guides

The SDK follows these; read them for the API's rules in Productive's words:

- [Filtering](https://developer.productive.io/guides/filtering)
- [Sorting](https://developer.productive.io/guides/sorting)
- [Pagination](https://developer.productive.io/guides/pagination)
- [Rate limits](https://developer.productive.io/guides/rate-limits)
- [Error handling](https://developer.productive.io/guides/error-handling)
- [API reference](https://developer.productive.io)
