<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Http\ContentType;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

it('only treats GET as safe to retry', function (Method $method, bool $safe) {
    expect($method->isSafe())->toBe($safe);
})->with([
    [Method::Get, true],
    [Method::Post, false],
    [Method::Patch, false],
    [Method::Put, false],
    [Method::Delete, false],
]);

it('has defaults for a request', function () {
    $request = new Request(Method::Get, 'tasks');

    expect($request->query)->toBe('')
        ->and($request->body)->toBeNull()
        ->and($request->contentType)->toBe(ContentType::JsonApi)
        ->and($request->expect)->toBe(Expect::Resource)
        ->and($request->operation)->toBe('')
        ->and($request->requiresOrganization)->toBeTrue()
        ->and($request->rateLimits)->toBe([])
        ->and($request->isAbsolute())->toBeFalse()
        ->and($request->describe())->toBe('GET tasks');
});

it('recognises absolute paths', function (string $path, bool $absolute) {
    expect((new Request(Method::Get, $path))->isAbsolute())->toBe($absolute);
})->with([
    ['https://api.productive.io/api/v2/tasks', true],
    ['http://api.productive.io/api/v2/tasks', true],
    ['tasks/https:', false],
]);

it('declares the documented rate limits', function () {
    expect(RateLimit::token())->toEqual(new RateLimit('token', 100, 10))
        ->and(RateLimit::reports())->toEqual(new RateLimit('reports', 10, 30));
});

it('uses the JSON:API media types', function () {
    expect(ContentType::JsonApi->value)->toBe('application/vnd.api+json')
        ->and(ContentType::JsonApiBulk->value)->toBe('application/vnd.api+json; ext=bulk');
});

it('normalizes response headers', function () {
    $response = new Response(200, ['X-RateLimit-Reset' => '5', 'Set-Cookie' => ['a', 'b'], 'Keyed' => [3 => 'c']], '');

    expect($response->header('x-ratelimit-reset'))->toBe('5')
        ->and($response->header('SET-COOKIE'))->toBe('a')
        ->and($response->header('keyed'))->toBe('c')
        ->and($response->header('missing'))->toBeNull()
        ->and($response->headers)->toBe(['x-ratelimit-reset' => ['5'], 'set-cookie' => ['a', 'b'], 'keyed' => ['c']]);
});

it('knows which statuses are successful', function (int $status, bool $successful) {
    expect((new Response($status))->successful())->toBe($successful);
})->with([[199, false], [200, true], [204, true], [299, true], [300, false], [404, false]]);

it('decodes a JSON object body', function () {
    expect((new Response(200, [], '{"data":{"id":"1"}}'))->json())->toBe(['data' => ['id' => '1']])
        ->and((new Response(200, [], '{}'))->json())->toBe([])
        ->and((new Response(204, [], "  \n"))->json())->toBe([])
        ->and((new Response(204))->isEmpty())->toBeTrue()
        ->and((new Response(200, [], '{}'))->isEmpty())->toBeFalse();
});

it('rejects bodies that are not a JSON object', function (string $body, string $message) {
    expect(fn() => (new Response(200, [], $body))->json())->toThrow(InvalidResponseException::class, $message);
})->with([
    ['<html>', 'Productive returned invalid JSON (HTTP 200)'],
    ['[1,2]', 'JSON body that is not an object (HTTP 200)'],
    ['"text"', 'JSON body that is not an object'],
]);

it('keeps the decoding error as previous exception', function () {
    try {
        (new Response(500, [], '{'))->json();
    } catch (InvalidResponseException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(JsonException::class);

        return;
    }

    $this->fail('No exception thrown.');
});
