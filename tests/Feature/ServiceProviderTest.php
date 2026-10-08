<?php

declare(strict_types=1);

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ThrottleInterface;
use Axyr\Productive\Http\CacheThrottle;
use Axyr\Productive\Http\NullThrottle;
use Axyr\Productive\ProductiveClient;
use Axyr\Productive\ProductiveFacade as Productive;
use Axyr\Productive\ProductiveServiceProvider;
use Axyr\Productive\Resources\Reports\Reports;
use Axyr\Productive\Resources\TaskResource;
use Axyr\Productive\Resources\TimeEntryResource;
use Illuminate\Http\Client\Request as HttpRequest;
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
