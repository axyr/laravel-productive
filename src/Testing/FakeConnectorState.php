<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Closure;

/**
 * What every view of one fake shares: the seeded responses and the recorded requests.
 *
 * @internal
 */
final class FakeConnectorState
{
    /** @var list<array{Request, string}> Each request with the organization it was sent for. */
    public array $recorded = [];

    public bool $preventStrayRequests = false;

    public int $nextId = 1;

    /**
     * @param  array<string, Response|ResponseSequence|Closure(Request): Response>  $responses
     */
    public function __construct(
        public array $responses = [],
    ) {}
}
