<?php

declare(strict_types=1);

namespace Tests;

use Axyr\Productive\ProductiveServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ProductiveServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('productive.token', 'test-token');
        $app['config']->set('productive.organization_id', '4242');
        $app['config']->set('productive.base_url', 'https://api.productive.test/api/v2');
    }
}
