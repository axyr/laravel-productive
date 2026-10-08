<?php

declare(strict_types=1);

namespace Axyr\Productive;

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Contracts\ThrottleInterface;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Http\CacheThrottle;
use Axyr\Productive\Http\Connector;
use Axyr\Productive\Http\NullThrottle;
use Axyr\Productive\Http\RetryPolicy;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class ProductiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/productive.php', 'productive');

        $this->app->scoped(ProductiveConfig::class, function (Application $app): ProductiveConfig {
            /** @var array<string, mixed> $config */
            $config = $app->make(ConfigRepository::class)->get('productive', []);

            return ProductiveConfig::fromArray($config);
        });

        $this->app->scoped(ThrottleInterface::class, function (Application $app): ThrottleInterface {
            $config = $app->make(ProductiveConfig::class);

            return $config->throttle
                ? new CacheThrottle($app->make(CacheFactory::class)->store($config->cacheStore))
                : new NullThrottle();
        });

        $this->registerClient();
    }

    private function registerClient(): void
    {
        $this->app->scoped(ProductiveClient::class, fn(Application $app): ProductiveClient => new ProductiveClient(
            $app->make(ProductiveConfig::class),
            fn(ProductiveConfig $config): ConnectorInterface => $app->bound(ConnectorInterface::class)
                ? $app->make(ConnectorInterface::class)
                : new Connector(
                    $config,
                    $app->make(HttpFactory::class),
                    $app->make(ThrottleInterface::class),
                    new RetryPolicy($config->maxAttempts, $config->maxRetryAfter),
                ),
            $app->make(ModelRegistry::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/productive.php' => $this->app->configPath('productive.php'),
            ], 'productive-config');
        }
    }
}
