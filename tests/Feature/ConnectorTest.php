<?php

declare(strict_types=1);

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Exceptions\ConfigurationException;
use Axyr\Productive\Exceptions\ConnectionException;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Exceptions\NotFoundException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Exceptions\ServerException;
use Axyr\Productive\Http\Connector;
use Axyr\Productive\Http\ContentType;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\NullThrottle;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\RetryPolicy;
use Axyr\Productive\ProductiveClient;
use Axyr\Productive\Version;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function connector(array $config = []): ConnectorInterface
{
    $config = new ProductiveConfig(...[
        'token' => 'test-token',
        'organizationId' => '4242',
        'baseUrl' => 'https://api.productive.test/api/v2',
        ...$config,
    ]);

    return new Connector($config, app(Factory::class), new NullThrottle(), new RetryPolicy($config->maxAttempts, $config->maxRetryAfter));
}

it('sends the authentication, organization and JSON:API headers', function () {
    fakeHttp(['*' => Http::response(['data' => null])]);

    $response = connector()->send(new Request(Method::Get, 'tasks/1', query: 'include=project'));

    expect($response->status)->toBe(200);
    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.productive.test/api/v2/tasks/1?include=project'
        && $request->header('X-Auth-Token') === ['test-token']
        && $request->header('X-Organization-Id') === ['4242']
        && $request->header('Accept') === ['application/vnd.api+json']
        && $request->header('User-Agent') === ['axyr/laravel-productive/' . Version::VERSION]
        && ! $request->hasHeader('X-Feature-Flags')
        && $request->body() === '');
});

it('sends a JSON:API body with its content type', function (ContentType $contentType) {
    fakeHttp(['*' => Http::response(['data' => null], 201)]);

    connector()->send(new Request(Method::Post, 'tasks', body: ['data' => ['type' => 'tasks', 'attributes' => ['estimate' => 1.0, 'url' => 'a/b']]], contentType: $contentType));

    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'POST'
        && $request->header('Content-Type') === [$contentType->value]
        && $request->body() === '{"data":{"type":"tasks","attributes":{"estimate":1.0,"url":"a/b"}}}');
})->with([ContentType::JsonApi, ContentType::JsonApiBulk]);

it('accepts any media type for binary downloads', function () {
    fakeHttp(['*' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf'])]);

    $response = connector()->send(new Request(Method::Get, 'proposals/1/signed_pdf', expect: Expect::Binary));

    expect($response->body)->toBe('%PDF');
    Http::assertSent(fn(HttpRequest $request): bool => $request->header('Accept') === ['*/*']);
});

it('sends configured feature flags', function () {
    fakeHttp(['*' => Http::response(['data' => []])]);

    connector(['featureFlags' => ['filteringSkipDatetimeCastToDate', 'other']])->send(new Request(Method::Get, 'tasks'));

    Http::assertSent(fn(HttpRequest $request): bool => $request->header('X-Feature-Flags') === ['filteringSkipDatetimeCastToDate,other']);
});

it('omits the organization header for public endpoints', function () {
    fakeHttp(['*' => Http::response(['data' => null])]);

    connector(['organizationId' => ''])->send(new Request(Method::Get, 'public/pages/abc', requiresOrganization: false));

    Http::assertSent(fn(HttpRequest $request): bool => ! $request->hasHeader('X-Organization-Id'));
});

it('refuses to send without credentials', function () {
    fakeHttp([]);

    expect(fn() => connector(['token' => ''])->send(new Request(Method::Get, 'tasks')))->toThrow(ConfigurationException::class)
        ->and(fn() => connector(['organizationId' => ''])->send(new Request(Method::Get, 'tasks')))->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});

it('follows absolute pagination links on the API host', function () {
    fakeHttp(['*' => Http::response(['data' => []])]);

    connector()->send(new Request(Method::Get, 'https://api.productive.test/api/v2/tasks?page[after]=abc&page[size]=200', query: 'ignored=1'));

    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === 'https://api.productive.test/api/v2/tasks?page[after]=abc&page[size]=200');
});

it('never sends the token to another host', function () {
    fakeHttp([]);

    expect(fn() => connector()->send(new Request(Method::Get, 'https://evil.test/api/v2/tasks')))
        ->toThrow(InvalidResponseException::class, 'Refusing to send credentials to "https://evil.test/api/v2/tasks"');

    Http::assertNothingSent();
});

it('throws a typed exception for error responses', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 404, 'title' => 'Record Not Found']]], 404)]);

    expect(fn() => connector()->send(new Request(Method::Get, 'tasks/9')))
        ->toThrow(NotFoundException::class, 'Productive API error 404 Record Not Found [GET tasks/9]');

    Http::assertSentCount(1);
});

it('retries a 429 after the reset window, also for writes', function () {
    fakeHttp(['*' => Http::sequence()
        ->push(['errors' => [['status' => 429, 'title' => 'Too many requests']]], 429, ['X-RateLimit-Reset' => '4'])
        ->push(['data' => ['type' => 'tasks', 'id' => '1']], 201)]);

    $response = connector()->send(new Request(Method::Post, 'tasks', body: ['data' => ['type' => 'tasks']]));

    expect($response->status)->toBe(201);
    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::for(4)->seconds()]);
});

it('gives up on a 429 that would take too long', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 429]]], 429, ['X-RateLimit-Reset' => '3600'])]);

    expect(fn() => connector(['maxRetryAfter' => 60])->send(new Request(Method::Get, 'tasks')))->toThrow(RateLimitException::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('retries server errors on reads with backoff until attempts run out', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 503]]], 503)]);

    expect(fn() => connector(['maxAttempts' => 3])->send(new Request(Method::Get, 'tasks')))->toThrow(ServerException::class);

    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(1)->seconds(), Sleep::for(2)->seconds()]);
});

it('recovers from a transient server error on a read', function () {
    fakeHttp(['*' => Http::sequence()->push('', 502)->push(['data' => []])]);

    expect(connector()->send(new Request(Method::Get, 'tasks'))->status)->toBe(200);
    Http::assertSentCount(2);
});

it('never retries a write after a server error', function () {
    fakeHttp(['*' => Http::response('', 500)]);

    expect(fn() => connector()->send(new Request(Method::Patch, 'invoices/1/send', body: ['data' => ['type' => 'invoices']])))->toThrow(ServerException::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('retries a dropped connection on reads', function () {
    fakeHttp(['*' => Http::sequence()->pushFailedConnection()->push(['data' => []])]);

    expect(connector()->send(new Request(Method::Get, 'tasks'))->status)->toBe(200);
    Sleep::assertSequence([Sleep::for(1)->seconds()]);
});

it('reports a dropped connection without retrying writes', function () {
    fakeHttp(['*' => Http::failedConnection('Connection refused')]);

    expect(fn() => connector()->send(new Request(Method::Post, 'tasks', body: [])))
        ->toThrow(ConnectionException::class, 'Could not reach Productive [POST tasks]: Connection refused');

    Sleep::assertNeverSlept();
});

it('is the connector the client uses by default', function () {
    expect(app(ProductiveClient::class)->connector())->toBeInstanceOf(Connector::class);
});
