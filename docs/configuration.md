[Back to documentation](README.md)

# Configuration

All settings come from `config/productive.php`. Publish it with:

```bash
php artisan vendor:publish --tag=productive-config
```

| Key | Environment variable | Default | |
|---|---|---|---|
| `token` | `PRODUCTIVE_API_TOKEN` | — | Sent as `X-Auth-Token`. |
| `organization_id` | `PRODUCTIVE_ORGANIZATION_ID` | — | Sent as `X-Organization-Id`. |
| `base_url` | `PRODUCTIVE_BASE_URL` | `https://api.productive.io/api/v2` | Must be an absolute `https` URL. |
| `timeout` | `PRODUCTIVE_TIMEOUT` | `30` | Seconds per request. |
| `connect_timeout` | `PRODUCTIVE_CONNECT_TIMEOUT` | `10` | Seconds. |
| `retry.max_attempts` | `PRODUCTIVE_RETRY_MAX_ATTEMPTS` | `3` | Including the first attempt. |
| `retry.max_retry_after` | `PRODUCTIVE_RETRY_MAX_RETRY_AFTER` | `60` | The longest rate-limit wait that is retried automatically, in seconds. |
| `throttle.enabled` | `PRODUCTIVE_THROTTLE` | `true` | Client-side rate limiting. |
| `throttle.cache_store` | `PRODUCTIVE_THROTTLE_CACHE_STORE` | default store | Use a shared store (redis, database) when several workers share a token. |
| `feature_flags` | `PRODUCTIVE_FEATURE_FLAGS` | — | Comma separated, sent as `X-Feature-Flags`. |

Credentials are checked when a request is sent, not when the app boots, so the package never breaks `php artisan` or CI runs that do not talk to Productive. A missing value throws a `ConfigurationException` that names the environment variable to set.

## Checking the connection

```bash
php artisan productive:ping
php artisan productive:ping --organization=12345
```

It reads one organization with the configured token and prints its name, or the reason Productive refused, with exit code 1. That makes it usable in a deploy check.

## Caching reference data

Reference data that rarely changes (people, service types, workflows) is worth caching. Productive's rate-limit guide recommends it. Cache the collection in your application, keyed by organization:

```php
$people = Cache::remember('productive.' . Productive::config()->organizationId . '.people', now()->addHour(), fn () => Productive::people()->query()->all());
```

Models are plain readonly objects, so any cache store can hold them. Clear the key after a deploy that upgrades the SDK.

## Multiple organizations and tokens

One client per call, without touching the global configuration:

```php
Productive::withOrganization('67890')->projects()->query()->all();

Productive::withToken($user->productive_token)->tasks()->find(1);
```

Each call returns a new client. The original client and the container binding are not changed.

## Feature flags

Productive casts datetime filter values to dates unless the `filteringSkipDatetimeCastToDate` flag is sent:

```dotenv
PRODUCTIVE_FEATURE_FLAGS=filteringSkipDatetimeCastToDate
```

## Octane

The config, throttle and client are scoped bindings, so they reset between requests under Octane, RoadRunner and FrankenPHP.

---

Next: [Querying](querying.md)
