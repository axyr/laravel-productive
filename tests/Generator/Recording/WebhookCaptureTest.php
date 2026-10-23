<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\WebhookCapture;

function capturedSignature(string $body, string $secret, string $timestamp = '1700000000', bool $base64 = false): string
{
    $digest = hash_hmac('sha256', $timestamp . '.' . $body, $secret, true);

    return 't=' . $timestamp . ',s=' . ($base64 ? base64_encode($digest) : bin2hex($digest));
}

it('records a delivery with its raw body, redacted JSON and verified signature', function () {
    $body = '{"event":"task.created","data":{"id":"1","type":"tasks","attributes":{"title":"Hi","api_token":"x"}}}';
    $headers = ['Content-Type' => 'application/json', 'Productive-Signature' => capturedSignature($body, 'tok'), 'Authorization' => 'Bearer mine', 'X-Custom' => 'yes'];

    $recording = (new WebhookCapture('tok'))->record(new DateTimeImmutable('2026-10-23T12:00:00+02:00'), 'POST', '/hooks?x=1', $headers, $body, 200);

    expect($recording)->toBe([
        'received_at' => '2026-10-23T12:00:00+02:00',
        'request' => ['method' => 'POST', 'uri' => '/hooks?x=1'],
        'headers' => ['Authorization' => '[redacted]', 'Content-Type' => 'application/json', 'Productive-Signature' => $headers['Productive-Signature'], 'X-Custom' => 'yes'],
        'body' => $body,
        'json' => ['event' => 'task.created', 'data' => ['id' => '1', 'type' => 'tasks', 'attributes' => ['title' => 'Hi', 'api_token' => '[redacted]']]],
        'signature' => ['timestamp' => '1700000000', 'signature' => explode('s=', $headers['Productive-Signature'])[1], 'valid' => 'hex'],
        'responded_with' => 200,
    ]);
});

it('tells a hex signature from a base64 one, and an invalid one', function (string $header, string|false $valid) {
    $recording = (new WebhookCapture('tok'))->record(new DateTimeImmutable(), 'POST', '/', ['productive-signature' => $header], '{}', 500);

    expect($recording['signature']['valid'])->toBe($valid)
        ->and($recording['responded_with'])->toBe(500);
})->with([
    'hex' => [capturedSignature('{}', 'tok'), 'hex'],
    'base64' => [capturedSignature('{}', 'tok', base64: true), 'base64'],
    'wrong secret' => [capturedSignature('{}', 'other'), false],
    'wrong timestamp' => ['t=1,' . explode(',', capturedSignature('{}', 'tok'))[1], false],
]);

it('does not check a signature without the token, and records none without the header', function () {
    $capture = new WebhookCapture();

    expect($capture->record(new DateTimeImmutable(), 'POST', '/', ['Productive-Signature' => 't=5,s=abc'], 'x', 200)['signature'])->toBe(['timestamp' => '5', 'signature' => 'abc', 'valid' => null])
        ->and($capture->record(new DateTimeImmutable(), 'POST', '/', ['Productive-Signature' => 't=5'], 'x', 200)['signature'])->toBeNull()
        ->and($capture->record(new DateTimeImmutable(), 'GET', '/', [], 'not json', 200))->toMatchArray(['signature' => null, 'json' => null, 'body' => 'not json']);
});
