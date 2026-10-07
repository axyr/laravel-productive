<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

use Axyr\Productive\Contracts\ThrottleInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

/**
 * Fixed-window counters in the Laravel cache, so every process sharing a cache store
 * (queue workers, Horizon, Octane) also shares one budget per token.
 */
final readonly class CacheThrottle implements ThrottleInterface
{
    public function __construct(
        private Repository $cache,
    ) {}

    public function acquire(Request $request, string $scope): void
    {
        foreach ([RateLimit::token(), ...$request->rateLimits] as $limit) {
            $this->acquireSlot($limit, $scope);
        }
    }

    private function acquireSlot(RateLimit $limit, string $scope): void
    {
        while (true) {
            $now = Carbon::now()->getTimestamp();
            $window = intdiv($now, $limit->seconds);
            $key = sprintf('productive:throttle:%s:%s:%d', $scope, $limit->name, $window);

            $this->cache->add($key, 0, $limit->seconds * 2);

            $count = $this->cache->increment($key);

            // A store that cannot count must not block the application: fail open.
            if (! is_int($count) || $count <= $limit->limit) {
                return;
            }

            Sleep::for(($window + 1) * $limit->seconds - $now)->seconds();
        }
    }
}
