<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Contract');

// No test may ever reach a real server: every request must match an Http::fake() response.
pest()->beforeEach(function (): void {
    Http::preventStrayRequests();
})->in('Feature', 'Contract');

pest()->beforeEach(function (): void {
    Sleep::fake(syncWithCarbon: true);
})->afterEach(function (): void {
    Sleep::fake(false);
    Carbon::setTestNow();
})->in('Unit', 'Feature', 'Contract');

/**
 * The base URL every Feature and Contract test is configured with.
 */
function apiUrl(string $path = ''): string
{
    return 'https://api.productive.test/api/v2/' . ltrim($path, '/');
}

/**
 * Fake the Laravel HTTP client. Stray requests are already prevented for every Feature and Contract test.
 *
 * @param  array<string, mixed>  $responses
 */
function fakeHttp(array $responses): Factory
{
    return Http::fake($responses);
}
