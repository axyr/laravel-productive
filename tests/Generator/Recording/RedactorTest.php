<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\Redactor;

it('blanks secret values at any depth and keeps everything else', function () {
    expect(Redactor::redact([
        'data' => [[
            'attributes' => [
                'name' => 'Hook',
                'secret' => 's3cr3t',
                'api_token' => 'tok',
                'Signature' => 'sig',
                'access_token' => null,
                'settings' => ['password' => 'pw', 'apiKey' => 'k', 'private_key' => 'pk', 'url' => 'https://example.test'],
            ],
        ]],
        0 => 'token',
    ]))->toBe([
        'data' => [[
            'attributes' => [
                'name' => 'Hook',
                'secret' => '[redacted]',
                'api_token' => '[redacted]',
                'Signature' => '[redacted]',
                'access_token' => null,
                'settings' => ['password' => '[redacted]', 'apiKey' => '[redacted]', 'private_key' => '[redacted]', 'url' => 'https://example.test'],
            ],
        ]],
        0 => 'token',
    ]);
});

it('leaves scalars untouched', function (mixed $value) {
    expect(Redactor::redact($value))->toBe($value);
})->with(['text', 12, null, true]);
