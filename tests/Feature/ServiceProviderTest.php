<?php

declare(strict_types=1);

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ThrottleInterface;
use Axyr\Productive\Exceptions\ServerException;
use Axyr\Productive\Http\CacheThrottle;
use Axyr\Productive\Http\NullThrottle;
use Axyr\Productive\ProductiveClient;
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\ProductiveServiceProvider;
use Axyr\Productive\Resources\Reports\Reports;
use Axyr\Productive\Resources\TaskResource;
use Axyr\Productive\Resources\TimeEntryResource;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

it('merges the package config', function () {
    expect(config('productive.retry.max_attempts'))->toBe(3)
        ->and(config('productive.throttle.enabled'))->toBeTrue()
        ->and(config('productive.token'))->toBe('test-token');
});

it('builds the config from the application config', function () {
    $config = app(ProductiveConfig::class);

    expect($config->token)->toBe('test-token')
        ->and($config->organizationId)->toBe('4242')
        ->and($config->baseUrl)->toBe('https://api.productive.test/api/v2');
});

it('publishes the config file', function () {
    $paths = ServiceProvider::pathsToPublish(ProductiveServiceProvider::class, 'productive-config');

    expect(array_values($paths))->toBe([config_path('productive.php')])
        ->and(array_key_first($paths))->toEndWith('config/productive.php')
        ->and(file_exists((string) array_key_first($paths)))->toBeTrue();
});

it('binds a cache throttle by default and a null throttle when disabled', function () {
    expect(app(ThrottleInterface::class))->toBeInstanceOf(CacheThrottle::class);

    config()->set('productive.throttle.enabled', false);
    app()->forgetScopedInstances();

    expect(app(ThrottleInterface::class))->toBeInstanceOf(NullThrottle::class);
});

it('scopes the client per request lifecycle', function () {
    $client = app(ProductiveClient::class);

    expect(app(ProductiveClient::class))->toBe($client);

    app()->forgetScopedInstances();

    expect(app(ProductiveClient::class))->not->toBe($client);
});

it('resolves the client through the facade', function () {
    expect(Productive::getFacadeRoot())->toBeInstanceOf(ProductiveClient::class)
        ->and(Productive::tasks())->toBeInstanceOf(TaskResource::class)
        ->and(Productive::timeEntries())->toBeInstanceOf(TimeEntryResource::class)
        ->and(Productive::reports())->toBeInstanceOf(Reports::class)
        ->and(Productive::config()->organizationId)->toBe('4242');
});

it('switches organization and token per call', function () {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::withOrganization('9999')->withToken('other-token')->tasks()->delete(1);
    Productive::tasks()->delete(2);

    Http::assertSentInOrder([
        fn(HttpRequest $request): bool => $request->header('X-Organization-Id') === ['9999'] && $request->header('X-Auth-Token') === ['other-token'],
        fn(HttpRequest $request): bool => $request->header('X-Organization-Id') === ['4242'] && $request->header('X-Auth-Token') === ['test-token'],
    ]);
});

it('reuses one connector per client', function () {
    $client = app(ProductiveClient::class);

    expect($client->connector())->toBe($client->connector())
        ->and($client->withOrganization('1')->connector())->not->toBe($client->connector());
});

it('reads every setting from the environment', function () {
    $env = [
        'PRODUCTIVE_API_TOKEN' => 'env-token',
        'PRODUCTIVE_ORGANIZATION_ID' => '777',
        'PRODUCTIVE_BASE_URL' => 'https://proxy.example.test/api/v2',
        'PRODUCTIVE_TIMEOUT' => '7',
        'PRODUCTIVE_CONNECT_TIMEOUT' => '3',
        'PRODUCTIVE_RETRY_MAX_ATTEMPTS' => '5',
        'PRODUCTIVE_RETRY_MAX_RETRY_AFTER' => '9',
        'PRODUCTIVE_THROTTLE' => 'false',
        'PRODUCTIVE_THROTTLE_CACHE_STORE' => 'redis',
        'PRODUCTIVE_FEATURE_FLAGS' => 'a, b',
    ];

    foreach ($env as $key => $value) {
        putenv($key . '=' . $value);
    }

    try {
        $config = ProductiveConfig::fromArray(require dirname(__DIR__, 2) . '/config/productive.php');
    } finally {
        foreach (array_keys($env) as $key) {
            putenv($key);
        }
    }

    expect(get_object_vars($config))->toBe([
        'token' => 'env-token',
        'organizationId' => '777',
        'baseUrl' => 'https://proxy.example.test/api/v2',
        'timeout' => 7,
        'connectTimeout' => 3,
        'maxAttempts' => 5,
        'maxRetryAfter' => 9,
        'throttle' => false,
        'cacheStore' => 'redis',
        'featureFlags' => ['a', 'b'],
    ]);
});

it('gives the connector the configured retry attempts', function () {
    config()->set('productive.retry.max_attempts', 2);
    app()->forgetScopedInstances();
    fakeHttp(['*' => Http::response('', 503)]);

    expect(fn() => Productive::tasks()->find(1))->toThrow(ServerException::class);

    Http::assertSentCount(2);
});

it('gives the connector the configured timeouts', function () {
    config()->set('productive.timeout', 7);
    config()->set('productive.connect_timeout', 3);
    app()->forgetScopedInstances();
    $options = [];
    fakeHttp(['*' => function (HttpRequest $request, array $requestOptions) use (&$options) {
        $options = $requestOptions;

        return Http::response(null, 204);
    }]);

    Productive::tasks()->delete(1);

    expect($options['timeout'] ?? null)->toBe(7)
        ->and($options['connect_timeout'] ?? null)->toBe(3);
});

it('counts requests in the configured cache store', function () {
    Carbon::setTestNow('2026-10-09 12:00:05');
    config()->set('cache.stores.throttle', ['driver' => 'array']);
    config()->set('productive.throttle.cache_store', 'throttle');
    app()->forgetScopedInstances();
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::tasks()->delete(1);

    $key = sprintf('productive:throttle:%s:token:%d', app(ProductiveConfig::class)->tokenFingerprint(), intdiv(Carbon::now()->getTimestamp(), 10));

    expect(Cache::store('throttle')->get($key))->toBe(1)
        ->and(Cache::store()->get($key))->toBeNull();
});
