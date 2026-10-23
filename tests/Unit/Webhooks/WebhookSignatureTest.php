<?php

declare(strict_types=1);

use Axyr\Productive\Webhooks\WebhookSignature;

it('signs a body the way Productive does and verifies it with any configured token', function () {
    $header = WebhookSignature::sign('{"a":1}', 'tok', 1700000000);

    expect($header)->toBe('t=1700000000,s=' . hash_hmac('sha256', '1700000000.{"a":1}', 'tok'))
        ->and((new WebhookSignature(['other', 'tok']))->verify($header, '{"a":1}', 1700000100))->toBeTrue()
        ->and((new WebhookSignature(['other']))->verify($header, '{"a":1}', 1700000100))->toBeFalse()
        ->and((new WebhookSignature(['tok']))->verify($header, '{"a":2}', 1700000100))->toBeFalse()
        ->and((new WebhookSignature(['tok']))->verify('t=1700000000,s=' . strtoupper(hash_hmac('sha256', '1700000000.{"a":1}', 'tok')), '{"a":1}', 1700000100))->toBeTrue();
});

it('rejects a missing, malformed or stale signature', function () {
    $signature = new WebhookSignature(['tok'], 300);
    $header = WebhookSignature::sign('x', 'tok', 1700000000);

    expect($signature->verify(null, 'x', 1700000000))->toBeFalse()
        ->and($signature->verify('s=abc', 'x', 1700000000))->toBeFalse()
        ->and($signature->verify('t=1700000000,s=zz', 'x', 1700000000))->toBeFalse()
        ->and($signature->verify($header, 'x', 1700000300))->toBeTrue()
        ->and($signature->verify($header, 'x', 1700000301))->toBeFalse()
        ->and($signature->verify($header, 'x', 1699999699))->toBeFalse()
        ->and($signature->verify(' ' . $header . ' ', 'x', 1700000000))->toBeTrue();
});

it('signs with the current time by default and verifies against it', function () {
    $header = WebhookSignature::sign('x', 'tok');

    expect((int) substr(explode(',', $header)[0], 2))->toBeGreaterThan(time() - 5)
        ->and((new WebhookSignature(['tok']))->verify($header, 'x'))->toBeTrue();
});
