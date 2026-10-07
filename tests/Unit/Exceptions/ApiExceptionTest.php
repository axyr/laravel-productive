<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\AuthenticationException;
use Axyr\Productive\Exceptions\AuthorizationException;
use Axyr\Productive\Exceptions\BadRequestException;
use Axyr\Productive\Exceptions\ConflictException;
use Axyr\Productive\Exceptions\GoneException;
use Axyr\Productive\Exceptions\MethodNotAllowedException;
use Axyr\Productive\Exceptions\NotAcceptableException;
use Axyr\Productive\Exceptions\NotFoundException;
use Axyr\Productive\Exceptions\PaymentRequiredException;
use Axyr\Productive\Exceptions\ProductiveException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Exceptions\ServerException;
use Axyr\Productive\Exceptions\UnsupportedMediaTypeException;
use Axyr\Productive\Exceptions\ValidationException;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

function errorResponse(int $status, array $errors, array $headers = []): Response
{
    return new Response($status, $headers, json_encode(['errors' => $errors]));
}

function apiException(Response $response, ?Request $request = null): ApiException
{
    return ApiException::fromResponse($request ?? new Request(Method::Get, 'tasks/1'), $response);
}

it('maps each documented status to its exception', function (int $status, string $class) {
    $exception = apiException(errorResponse($status, [['status' => $status, 'title' => 'Error']]));

    expect($exception)->toBeInstanceOf($class)
        ->and($exception)->toBeInstanceOf(ProductiveException::class)
        ->and($exception->status())->toBe($status)
        ->and($exception->getCode())->toBe($status);
})->with([
    [400, BadRequestException::class],
    [401, AuthenticationException::class],
    [402, PaymentRequiredException::class],
    [403, AuthorizationException::class],
    [404, NotFoundException::class],
    [405, MethodNotAllowedException::class],
    [406, NotAcceptableException::class],
    [409, ConflictException::class],
    [410, GoneException::class],
    [415, UnsupportedMediaTypeException::class],
    [422, ValidationException::class],
    [429, RateLimitException::class],
    [500, ServerException::class],
    [502, ServerException::class],
    [503, ServerException::class],
]);

it('uses the base class for undocumented statuses', function (int $status) {
    expect(get_class(apiException(errorResponse($status, []))))->toBe(ApiException::class);
})->with([418, 499]);

it('reads numeric strings in rate limit meta', function () {
    /** @var RateLimitException $exception */
    $exception = apiException(errorResponse(429, [['limit' => '1800', 'period' => '3600']]));

    expect($exception->limit())->toBe(1800)
        ->and($exception->period())->toBe(3600);
});

it('builds a readable message without credentials', function () {
    $exception = apiException(
        errorResponse(422, [
            ['status' => 422, 'title' => 'Invalid Attribute', 'detail' => "can't be blank", 'source' => ['pointer' => '/data/attributes/title']],
            ['status' => 422, 'title' => 'Invalid Attribute', 'detail' => 'due_date is invalid', 'source' => ['pointer' => '/data/attributes/due_date']],
            ['status' => 422, 'title' => 'Invalid Attribute', 'detail' => "can't be blank", 'source' => ['pointer' => '/data/attributes/title']],
        ]),
        new Request(Method::Patch, 'tasks/1'),
    );

    expect($exception->getMessage())->toBe("Productive API error 422 Invalid Attribute: title can't be blank; due_date is invalid [PATCH tasks/1]");
});

it('builds a message for errors without title or detail', function () {
    expect(apiException(errorResponse(500, [['status' => 500]]))->getMessage())->toBe('Productive API error 500 [GET tasks/1]')
        ->and(apiException(new Response(502, [], '<html>Bad gateway</html>'))->getMessage())->toBe('Productive API error 502 [GET tasks/1]')
        ->and(apiException(errorResponse(404, [['title' => 'Record Not Found']]))->getMessage())->toBe('Productive API error 404 Record Not Found [GET tasks/1]');
});

it('keeps the request, response and parsed errors', function () {
    $request = new Request(Method::Get, 'tasks/1');
    $response = errorResponse(404, [['status' => 404, 'title' => 'Record Not Found', 'detail' => 'The requested record was not found']]);
    $exception = ApiException::fromResponse($request, $response);

    expect($exception->request)->toBe($request)
        ->and($exception->response)->toBe($response)
        ->and($exception->errors)->toHaveCount(1)
        ->and($exception->firstError()?->title)->toBe('Record Not Found');
});

it('ignores malformed error payloads', function (Response $response) {
    $exception = apiException($response);

    expect($exception->errors)->toBe([])
        ->and($exception->firstError())->toBeNull();
})->with([
    'invalid json' => [new Response(500, [], '{')],
    'errors not a list' => [new Response(500, [], '{"errors":"boom"}')],
    'empty body' => [new Response(500)],
]);

it('skips error entries that are not objects', function () {
    $exception = apiException(new Response(400, [], '{"errors":["x",{"title":"Unsupported Sort"}]}'));

    expect($exception->errors)->toHaveCount(1)
        ->and($exception->errors[0]->title)->toBe('Unsupported Sort');
});

it('finds errors by code or title', function () {
    $exception = apiException(errorResponse(400, [['title' => 'Unsupported Sort'], ['code' => 'keyset_unsupported_sort']]));

    expect($exception->hasError('keyset_unsupported_sort'))->toBeTrue()
        ->and($exception->hasError('Unsupported Sort'))->toBeTrue()
        ->and($exception->hasError('keyset_conflict'))->toBeFalse();
});

it('groups validation messages by attribute', function () {
    $exception = apiException(errorResponse(422, [
        ['detail' => "can't be blank", 'source' => ['pointer' => '/data/attributes/title']],
        ['detail' => 'is too long', 'source' => ['pointer' => '/data/attributes/title']],
        ['title' => 'Project is archived'],
    ]));

    expect($exception)->toBeInstanceOf(ValidationException::class);
    /** @var ValidationException $exception */
    expect($exception->messages())->toBe([
        'title' => ["can't be blank", 'is too long'],
        'base' => ['Project is archived'],
    ])
        ->and($exception->first('title'))->toBe("can't be blank")
        ->and($exception->first('due_date'))->toBeNull();
});

it('reads the rate limit reset and limits', function () {
    /** @var RateLimitException $exception */
    $exception = apiException(errorResponse(429, [['status' => 429, 'title' => 'Server time limit exceeded', 'limit' => 1800, 'period' => 3600]], ['X-RateLimit-Reset' => '12.2']));

    expect($exception->retryAfter())->toBe(13)
        ->and($exception->isServerTimeLimit())->toBeTrue()
        ->and($exception->limit())->toBe(1800)
        ->and($exception->period())->toBe(3600);
});

it('falls back to Retry-After and handles missing values', function () {
    /** @var RateLimitException $withRetryAfter */
    $withRetryAfter = apiException(errorResponse(429, [['title' => 'Too many requests']], ['Retry-After' => '7']));
    /** @var RateLimitException $bare */
    $bare = apiException(new Response(429));
    /** @var RateLimitException $negative */
    $negative = apiException(errorResponse(429, [], ['X-RateLimit-Reset' => '-3']));
    /** @var RateLimitException $invalid */
    $invalid = apiException(errorResponse(429, [], ['X-RateLimit-Reset' => 'soon']));

    expect($withRetryAfter->retryAfter())->toBe(7)
        ->and($withRetryAfter->isServerTimeLimit())->toBeFalse()
        ->and($withRetryAfter->limit())->toBeNull()
        ->and($bare->retryAfter())->toBeNull()
        ->and($bare->isServerTimeLimit())->toBeFalse()
        ->and($bare->period())->toBeNull()
        ->and($negative->retryAfter())->toBe(0)
        ->and($invalid->retryAfter())->toBeNull();
});
