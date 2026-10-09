<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\Redactor;

it('blanks secret strings at any depth and keeps everything else', function () {
    expect(Redactor::redact([
        'data' => [[
            'attributes' => [
                'name' => 'Hook',
                'secret' => 's3cr3t',
                'api_token' => 'tok',
                'Signature' => 'sig',
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
                'settings' => ['password' => '[redacted]', 'apiKey' => '[redacted]', 'private_key' => '[redacted]', 'url' => 'https://example.test'],
            ],
        ]],
        0 => 'token',
    ]);
});

it('keeps metadata about secrets with its value and type', function () {
    $attributes = [
        'token_expires_at' => '2026-10-09T12:00:00.000+02:00',
        'password_changed_on' => '2026-10-01',
        'api_key_id' => '12',
        'secret_ids' => ['1', '2'],
        'tokens_count' => 3,
        'password_set' => true,
        'access_token' => null,
        'secret' => ['nested' => 'kept'],
    ];

    expect(Redactor::redact($attributes))->toBe($attributes);
});

it('leaves scalars untouched', function (mixed $value) {
    expect(Redactor::redact($value))->toBe($value);
})->with(['text', 12, null, true]);
