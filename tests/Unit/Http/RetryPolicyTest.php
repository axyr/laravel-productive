<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Axyr\Productive\Http\RetryPolicy;

function failure(int $status, array $headers = []): ApiException
{
    return ApiException::fromResponse(new Request(Method::Get, 'tasks'), new Response($status, $headers));
}

it('waits for the rate limit reset on a 429, for any method', function (Method $method) {
    $policy = new RetryPolicy(maxAttempts: 3, maxRetryAfter: 60);

    expect($policy->delayAfterError(new Request($method, 'tasks'), failure(429, ['X-RateLimit-Reset' => '9']), 1))->toBe(9);
})->with([Method::Get, Method::Post, Method::Patch, Method::Delete]);

it('backs off exponentially on a 429 without a reset header', function () {
    $policy = new RetryPolicy(maxAttempts: 5);
    $request = new Request(Method::Get, 'tasks');

    expect($policy->delayAfterError($request, failure(429), 1))->toBe(1)
        ->and($policy->delayAfterError($request, failure(429), 2))->toBe(2)
        ->and($policy->delayAfterError($request, failure(429), 3))->toBe(4)
        ->and($policy->delayAfterError($request, failure(429), 4))->toBe(8);
});

it('gives up when the reset is further away than allowed', function () {
    $policy = new RetryPolicy(maxAttempts: 3, maxRetryAfter: 30);
    $request = new Request(Method::Get, 'tasks');

    expect($policy->delayAfterError($request, failure(429, ['X-RateLimit-Reset' => '31']), 1))->toBeNull()
        ->and($policy->delayAfterError($request, failure(429, ['X-RateLimit-Reset' => '30']), 1))->toBe(30);
});

it('retries server errors for reads only', function () {
    $policy = new RetryPolicy(maxAttempts: 3);

    expect($policy->delayAfterError(new Request(Method::Get, 'tasks'), failure(503), 1))->toBe(1)
        ->and($policy->delayAfterError(new Request(Method::Get, 'tasks'), failure(500), 2))->toBe(2)
        ->and($policy->delayAfterError(new Request(Method::Post, 'tasks'), failure(503), 1))->toBeNull()
        ->and($policy->delayAfterError(new Request(Method::Patch, 'tasks/1'), failure(500), 1))->toBeNull();
});

it('never retries client errors', function (int $status) {
    expect((new RetryPolicy())->delayAfterError(new Request(Method::Get, 'tasks'), failure($status), 1))->toBeNull();
})->with([400, 401, 403, 404, 409, 422]);

it('stops at the maximum number of attempts', function () {
    $policy = new RetryPolicy(maxAttempts: 2);
    $request = new Request(Method::Get, 'tasks');

    expect($policy->delayAfterError($request, failure(429, ['X-RateLimit-Reset' => '1']), 1))->toBe(1)
        ->and($policy->delayAfterError($request, failure(429, ['X-RateLimit-Reset' => '1']), 2))->toBeNull()
        ->and($policy->delayAfterError($request, failure(503), 2))->toBeNull()
        ->and($policy->delayAfterConnectionFailure($request, 2))->toBeNull();
});

it('retries connection failures for reads only', function () {
    $policy = new RetryPolicy(maxAttempts: 3);

    expect($policy->delayAfterConnectionFailure(new Request(Method::Get, 'tasks'), 1))->toBe(1)
        ->and($policy->delayAfterConnectionFailure(new Request(Method::Get, 'tasks'), 2))->toBe(2)
        ->and($policy->delayAfterConnectionFailure(new Request(Method::Post, 'tasks'), 1))->toBeNull();
});

it('does not retry when only one attempt is allowed', function () {
    $policy = new RetryPolicy(maxAttempts: 1);

    expect($policy->delayAfterError(new Request(Method::Get, 'tasks'), failure(429, ['X-RateLimit-Reset' => '1']), 1))->toBeNull();
});
