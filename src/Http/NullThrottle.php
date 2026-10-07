<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

use Axyr\Productive\Contracts\ThrottleInterface;

final readonly class NullThrottle implements ThrottleInterface
{
    public function acquire(Request $request, string $scope): void {}
}
