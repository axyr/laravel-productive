<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Create a token under Settings → API integrations in Productive. The
    | organization ID is shown on the same page and is sent as the
    | X-Organization-Id header on every request.
    |
    */

    'token' => env('PRODUCTIVE_API_TOKEN'),

    'organization_id' => env('PRODUCTIVE_ORGANIZATION_ID'),

    'base_url' => env('PRODUCTIVE_BASE_URL', 'https://api.productive.io/api/v2'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => env('PRODUCTIVE_TIMEOUT', 30),

    'connect_timeout' => env('PRODUCTIVE_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | A 429 is retried for every method once the X-RateLimit-Reset window has
    | passed, as long as that wait is at most max_retry_after seconds. Server
    | errors and connection failures are only retried for GET requests, so a
    | non-idempotent call (send an invoice, approve time) is never repeated.
    |
    | Retries sleep in the calling process: in the worst case a call blocks for
    | (max_attempts - 1) × max_retry_after seconds, two minutes by default. For
    | web requests, lower max_retry_after (or max_attempts) and let queued jobs
    | do the waiting.
    |
    */

    'retry' => [
        'max_attempts' => env('PRODUCTIVE_RETRY_MAX_ATTEMPTS', 3),
        'max_retry_after' => env('PRODUCTIVE_RETRY_MAX_RETRY_AFTER', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-side throttling
    |--------------------------------------------------------------------------
    |
    | Reduces 429s by pacing requests to Productive's per-token limits (100 per
    | 10 seconds, reports 10 per 30 seconds). Counters live in the cache, so use
    | a shared store (redis, database) when several workers share one token.
    | Windows are fixed to the clock, so a burst across a window boundary can
    | still exceed the limit; the 429 retry absorbs those.
    |
    */

    'throttle' => [
        'enabled' => env('PRODUCTIVE_THROTTLE', true),
        'cache_store' => env('PRODUCTIVE_THROTTLE_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    | Sent as the X-Feature-Flags header. Comma separated, for example
    | "filteringSkipDatetimeCastToDate" to filter datetimes with time precision.
    |
    */

    'feature_flags' => env('PRODUCTIVE_FEATURE_FLAGS', ''),

];
