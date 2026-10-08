<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

/**
 * A client-side rate limit bucket, applied per API token.
 *
 * @see https://developer.productive.io/guides/rate-limits
 */
final readonly class RateLimit
{
    public function __construct(
        public string $name,
        public int $limit,
        public int $seconds,
    ) {}

    /**
     * Every request: 100 requests per 10 seconds per token.
     */
    public static function token(): self
    {
        return new self('token', 100, 10);
    }

    /**
     * The /reports endpoints: 10 requests per 30 seconds per token.
     */
    public static function reports(): self
    {
        return new self('reports', 10, 30);
    }
}
