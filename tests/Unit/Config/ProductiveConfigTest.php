<?php

declare(strict_types=1);

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Exceptions\ConfigurationException;

it('uses sensible defaults', function () {
    $config = new ProductiveConfig('token', '1');

    expect($config->baseUrl)->toBe('https://api.productive.io/api/v2')
        ->and($config->timeout)->toBe(30)
        ->and($config->connectTimeout)->toBe(10)
        ->and($config->maxAttempts)->toBe(3)
        ->and($config->maxRetryAfter)->toBe(60)
        ->and($config->throttle)->toBeTrue()
        ->and($config->cacheStore)->toBeNull()
        ->and($config->featureFlags)->toBe([]);
});

it('parses a config array including env strings', function () {
    $config = ProductiveConfig::fromArray([
        'token' => 'abc',
        'organization_id' => '123',
        'base_url' => 'https://example.test/api/v2',
        'timeout' => '45',
        'connect_timeout' => 5,
        'retry' => ['max_attempts' => '5', 'max_retry_after' => '120'],
        'throttle' => ['enabled' => 'false', 'cache_store' => 'redis'],
        'feature_flags' => 'filteringSkipDatetimeCastToDate, other ,',
    ]);

    expect($config->token)->toBe('abc')
        ->and($config->organizationId)->toBe('123')
        ->and($config->baseUrl)->toBe('https://example.test/api/v2')
        ->and($config->timeout)->toBe(45)
        ->and($config->connectTimeout)->toBe(5)
        ->and($config->maxAttempts)->toBe(5)
        ->and($config->maxRetryAfter)->toBe(120)
        ->and($config->throttle)->toBeFalse()
        ->and($config->cacheStore)->toBe('redis')
        ->and($config->featureFlags)->toBe(['filteringSkipDatetimeCastToDate', 'other']);
});

it('falls back to defaults for missing or malformed values', function () {
    $config = ProductiveConfig::fromArray([
        'token' => null,
        'base_url' => '',
        'timeout' => 'soon',
        'retry' => 'nope',
        'throttle' => ['enabled' => true, 'cache_store' => ''],
        'feature_flags' => 42,
    ]);

    expect($config->token)->toBe('')
        ->and($config->organizationId)->toBe('')
        ->and($config->baseUrl)->toBe(ProductiveConfig::DEFAULT_BASE_URL)
        ->and($config->timeout)->toBe(30)
        ->and($config->maxAttempts)->toBe(3)
        ->and($config->throttle)->toBeTrue()
        ->and($config->cacheStore)->toBeNull()
        ->and($config->featureFlags)->toBe([]);
});

it('applies every default when parsing an empty array', function () {
    $config = ProductiveConfig::fromArray([]);

    expect($config->timeout)->toBe(30)
        ->and($config->connectTimeout)->toBe(10)
        ->and($config->maxAttempts)->toBe(3)
        ->and($config->maxRetryAfter)->toBe(60)
        ->and($config->throttle)->toBeTrue()
        ->and($config->cacheStore)->toBeNull()
        ->and($config->featureFlags)->toBe([]);
});

it('accepts a single attempt', function () {
    expect((new ProductiveConfig('token', '1', maxAttempts: 1))->maxAttempts)->toBe(1);
});

it('accepts feature flags as an array', function () {
    $config = ProductiveConfig::fromArray(['feature_flags' => ['a', '', 7, ' b ']]);

    expect($config->featureFlags)->toBe(['a', 'b']);
});

it('parses boolean strings', function (mixed $value, bool $expected) {
    expect(ProductiveConfig::fromArray(['throttle' => ['enabled' => $value]])->throttle)->toBe($expected);
})->with([
    ['true', true],
    ['1', true],
    ['yes', true],
    ['false', false],
    ['0', false],
    ['no', false],
    ['off', false],
    [' OFF ', false],
    ['', false],
    [0, false],
    [1, true],
]);

it('rejects a base URL that is not absolute https', function (string $url) {
    new ProductiveConfig('token', '1', baseUrl: $url);
})->with([
    'http' => 'http://api.productive.io/api/v2',
    'relative' => '/api/v2',
    'garbage' => 'not a url',
    'https without host' => 'https://',
])->throws(ConfigurationException::class, 'must be an absolute https URL');

it('rejects fewer than one attempt', function () {
    new ProductiveConfig('token', '1', maxAttempts: 0);
})->throws(ConfigurationException::class, 'at least 1');

it('rejects negative timeouts', function (array $arguments) {
    new ProductiveConfig('token', '1', ...$arguments);
})->with([
    [['timeout' => -1]],
    [['connectTimeout' => -1]],
    [['maxRetryAfter' => -1]],
])->throws(ConfigurationException::class, 'cannot be negative');

it('accepts zero timeouts', function () {
    expect((new ProductiveConfig('token', '1', timeout: 0, connectTimeout: 0, maxRetryAfter: 0))->timeout)->toBe(0);
});

it('requires a token and an organization when sending', function () {
    expect(fn() => (new ProductiveConfig('', '1'))->assertHasCredentials())
        ->toThrow(ConfigurationException::class, 'The Productive token is not configured. Set PRODUCTIVE_API_TOKEN')
        ->and(fn() => (new ProductiveConfig('token', ''))->assertHasCredentials())
        ->toThrow(ConfigurationException::class, 'The Productive organization_id is not configured. Set PRODUCTIVE_ORGANIZATION_ID');
});

it('does not require an organization for public endpoints', function () {
    (new ProductiveConfig('token', ''))->assertHasCredentials(requiresOrganization: false);

    expect(true)->toBeTrue();
});

it('switches organization and token without mutating the original', function () {
    $config = new ProductiveConfig('token', '1', timeout: 12);
    $other = $config->withOrganization('2')->withToken('other');

    expect($config->organizationId)->toBe('1')
        ->and($config->token)->toBe('token')
        ->and($other->organizationId)->toBe('2')
        ->and($other->token)->toBe('other')
        ->and($other->timeout)->toBe(12);
});

it('builds URLs and recognises its own URLs', function () {
    $config = new ProductiveConfig('token', '1', baseUrl: 'https://api.productive.io/api/v2/');

    expect($config->url('/tasks/1'))->toBe('https://api.productive.io/api/v2/tasks/1')
        ->and($config->ownsUrl('https://api.productive.io/api/v2/tasks?page[after]=x'))->toBeTrue()
        ->and($config->ownsUrl('https://api.productive.io/api/v2'))->toBeFalse()
        ->and($config->ownsUrl('https://api.productive.io/api/v2-evil/tasks'))->toBeFalse()
        ->and($config->ownsUrl('https://evil.test/api/v2/tasks'))->toBeFalse();
});

it('fingerprints the token without exposing it', function () {
    $fingerprint = (new ProductiveConfig('secret-token', '1'))->tokenFingerprint();

    expect($fingerprint)->toHaveLength(16)
        ->and($fingerprint)->toBe(substr(hash('sha256', 'secret-token'), 0, 16))
        ->and($fingerprint)->not->toContain('secret');
});
