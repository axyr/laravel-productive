<?php

declare(strict_types=1);

namespace Axyr\Productive\Contracts;

use Axyr\Productive\Http\Request;

interface ThrottleInterface
{
    /**
     * Block until the request fits within its rate limit buckets.
     *
     * @param  string  $scope  Identifies whose budget is consumed, typically the token fingerprint.
     */
    public function acquire(Request $request, string $scope): void;
}
