<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 429. Thrown once automatic retries are exhausted or the wait exceeds `retry.max_retry_after`.
 *
 * In a queued job, release the job instead of failing it:
 *
 *     catch (RateLimitException $e) { $this->release($e->retryAfter() ?? 30); }
 */
class RateLimitException extends ApiException
{
    /**
     * Seconds until the limit window resets, from the X-RateLimit-Reset header.
     */
    public function retryAfter(): ?int
    {
        $reset = $this->response->header('X-RateLimit-Reset') ?? $this->response->header('Retry-After');

        return $reset !== null && is_numeric($reset) ? max(0, (int) ceil((float) $reset)) : null;
    }

    /**
     * True when the organization's processing-time budget is exhausted rather than the request count.
     */
    public function isServerTimeLimit(): bool
    {
        $title = $this->firstError()->title ?? null;

        return $title !== null && str_contains(strtolower($title), 'server time');
    }

    public function limit(): ?int
    {
        return $this->metaInt('limit');
    }

    public function period(): ?int
    {
        return $this->metaInt('period');
    }

    private function metaInt(string $key): ?int
    {
        $value = $this->firstError()->meta[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
