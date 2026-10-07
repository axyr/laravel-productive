[Back to documentation](README.md)

# Errors, retries and rate limits

This SDK never swallows an error. Every failed request throws, and every exception extends `Axyr\Productive\Exceptions\ProductiveException`:

```
ProductiveException (RuntimeException)
├── ConfigurationException          token / organization missing, invalid base URL
├── ConnectionException             no response (DNS, TLS, timeout)
├── InvalidQueryException           a query Productive would reject, caught before sending
├── InvalidResponseException        a successful status with a body that is not the expected JSON:API document
├── RelationshipNotIncludedException
└── ApiException                    Productive answered with an error status
    ├── BadRequestException         400  unsupported filter, sort or group
    ├── AuthenticationException     401
    ├── PaymentRequiredException    402
    ├── AuthorizationException      403
    ├── NotFoundException           404
    ├── MethodNotAllowedException   405
    ├── NotAcceptableException      406
    ├── ConflictException           409  e.g. rejecting an approved time entry
    ├── GoneException               410
    ├── UnsupportedMediaTypeException 415
    ├── ValidationException         422
    ├── RateLimitException          429
    └── ServerException             5xx
```

An `ApiException` carries the request (`$e->request`, never the token), the response (`$e->response`) and the parsed JSON:API errors (`$e->errors`, `$e->firstError()`, `$e->hasError('keyset_conflict')`). Its message includes the details:

```
Productive API error 422 Invalid Attribute: title can't be blank [POST tasks]
```

## Validation errors

```php
try {
    Productive::tasks()->create($data);
} catch (ValidationException $e) {
    $e->messages();        // ['title' => ["can't be blank"], 'base' => [...]]
    $e->first('title');    // "can't be blank"
}
```

## Retries

| Failure | GET | POST / PATCH / PUT / DELETE |
|---|---|---|
| 429 Too Many Requests | retried after `X-RateLimit-Reset` | retried after `X-RateLimit-Reset` |
| 5xx | retried with backoff (1 s, 2 s, 4 s…) | **not retried** |
| Connection failure | retried with backoff | **not retried** |
| Other 4xx | not retried | not retried |

A 429 is safe to retry for every method because Productive rejects the request before doing any work. A write that failed with a 5xx may already have been applied, and repeating "send invoice" is worse than reporting the error. Attempts are capped by `retry.max_attempts`, and a 429 whose reset is further away than `retry.max_retry_after` seconds is thrown straight away.

## Rate limits

Productive enforces, per token, 100 requests per 10 seconds and 10 report requests per 30 seconds. Per organization, it enforces 4,000 requests per 30 minutes and a processing-time budget (30 minutes per hour, 6 hours per day).

The client-side throttle counts requests in the Laravel cache and waits before a request would exceed a per-token limit. Use a shared cache store so all workers share one budget. The organization limits cannot be known client-side; they surface as a `RateLimitException`, where `isServerTimeLimit()` tells the processing-time limit apart.

### In queued jobs

Release the job instead of failing it:

```php
public function handle(): void
{
    try {
        Productive::timeEntries()->bulkCreate($this->entries);
    } catch (RateLimitException $e) {
        $this->release($e->retryAfter() ?? 60);
    }
}
```

---

Previous: [Reports](reports.md) · Next: [Testing](testing.md)
