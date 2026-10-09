<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing\Concerns;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Http\Response;
use Axyr\Productive\ProductiveClient;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\ProductiveFake;
use Axyr\Productive\Testing\ResponseSequence;
use Closure;
use LogicException;

/**
 * Productive::fake() for the facade.
 */
trait FakesProductive
{
    /**
     * Swap the HTTP layer for an in-memory fake that records every request.
     *
     * @param  array<string, Response|ResponseSequence|Closure(\Axyr\Productive\Http\Request): Response>  $responses  Keyed by operation ("tasks.show", "tasks.*") or "METHOD path" ("GET tasks/*").
     */
    public static function fake(array $responses = []): ProductiveFake
    {
        $fake = new ProductiveFake(new FakeConnector($responses));

        $app = static::getFacadeApplication() ?? throw new LogicException('Productive::fake() needs a booted Laravel application.');
        $app->instance(ConnectorInterface::class, $fake->connector());
        $app->forgetInstance(ProductiveClient::class);
        static::clearResolvedInstance(ProductiveClient::class);

        return $fake;
    }
}
