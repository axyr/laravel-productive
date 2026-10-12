<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('reports the organization it reached', function () {
    fakeHttp(['*' => Http::response(['data' => [['type' => 'organizations', 'id' => '4242', 'attributes' => ['name' => 'Acme']]]])]);

    $this->artisan('productive:ping')
        ->expectsOutputToContain('Connected to Productive: Acme (organization 4242).')
        ->assertSuccessful();

    Http::assertSent(fn(HttpRequest $request): bool => urldecode($request->url()) === apiUrl('organizations?page[size]=1')
        && $request->header('X-Organization-Id') === ['4242']);
});

it('checks another organization on request', function () {
    fakeHttp(['*' => Http::response(['data' => []])]);

    $this->artisan('productive:ping', ['--organization' => '77'])
        ->expectsOutputToContain('Connected to Productive: unnamed organization (organization 77).')
        ->assertSuccessful();

    Http::assertSent(fn(HttpRequest $request): bool => $request->header('X-Organization-Id') === ['77']);
});

it('fails with the reason when Productive refuses', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => '401', 'title' => 'Unauthorized']]], 401)]);

    $this->artisan('productive:ping')
        ->expectsOutputToContain('Productive did not accept the request: Productive API error 401 Unauthorized')
        ->assertFailed();
});

it('fails when the token is not configured', function () {
    config()->set('productive.token', null);
    app()->forgetScopedInstances();

    $this->artisan('productive:ping')
        ->expectsOutputToContain('PRODUCTIVE_API_TOKEN')
        ->assertFailed();
});

it('uses the configured organization when the option is empty', function () {
    fakeHttp(['*' => Http::response(['data' => []])]);

    $this->artisan('productive:ping', ['--organization' => ''])
        ->expectsOutputToContain('(organization 4242)')
        ->assertSuccessful();
});
