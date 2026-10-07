<?php

declare(strict_types=1);

namespace Axyr\Productive;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Http\Response;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\ProductiveFake;
use Axyr\Productive\Testing\ResponseSequence;
use Closure;
use Illuminate\Support\Facades\Facade;
use LogicException;

/**
 * @method static \Axyr\Productive\ProductiveClient withOrganization(string $organizationId)
 * @method static \Axyr\Productive\ProductiveClient withToken(string $token)
 * @method static \Axyr\Productive\Config\ProductiveConfig config()
 * @method static \Axyr\Productive\Contracts\ConnectorInterface connector()
 * @method static \Axyr\Productive\Resources\TaskResource tasks()
 * @method static \Axyr\Productive\Resources\TimeEntryResource timeEntries()
 * @method static \Axyr\Productive\Resources\Reports\Reports reports()
 *
 * @see ProductiveClient
 */
class ProductiveFacade extends Facade
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
        $app->forgetScopedInstances();
        static::clearResolvedInstance(ProductiveClient::class);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ProductiveClient::class;
    }
}
