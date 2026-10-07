<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Exceptions\ServerException;

/**
 * Decides whether, and after how many seconds, a failed attempt is retried.
 *
 * - 429 is retried for every method: the API rejected the request before doing any work.
 * - 5xx and connection failures are only retried for GET: a write may already have been applied.
 */
final readonly class RetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $maxRetryAfter = 60,
    ) {}

    /**
     * @return int|null Seconds to wait before the next attempt, or null to give up.
     */
    public function delayAfterError(Request $request, ApiException $exception, int $attempt): ?int
    {
        if ($attempt >= $this->maxAttempts) {
            return null;
        }

        if ($exception instanceof RateLimitException) {
            return $this->rateLimitDelay($exception, $attempt);
        }

        return $this->serverErrorDelay($request, $exception, $attempt);
    }

    private function serverErrorDelay(Request $request, ApiException $exception, int $attempt): ?int
    {
        return $exception instanceof ServerException && $request->method->isSafe() ? $this->backoff($attempt) : null;
    }

    /**
     * @return int|null Seconds to wait before the next attempt, or null to give up.
     */
    public function delayAfterConnectionFailure(Request $request, int $attempt): ?int
    {
        if ($attempt >= $this->maxAttempts || ! $request->method->isSafe()) {
            return null;
        }

        return $this->backoff($attempt);
    }

    private function rateLimitDelay(RateLimitException $exception, int $attempt): ?int
    {
        $delay = $exception->retryAfter() ?? $this->backoff($attempt);

        return $delay <= $this->maxRetryAfter ? $delay : null;
    }

    /**
     * Exponential backoff: 1, 2, 4, 8 … seconds.
     */
    private function backoff(int $attempt): int
    {
        return 2 ** ($attempt - 1);
    }
}
