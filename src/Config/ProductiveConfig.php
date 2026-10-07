<?php

declare(strict_types=1);

namespace Axyr\Productive\Config;

use Axyr\Productive\Exceptions\ConfigurationException;

readonly class ProductiveConfig
{
    public const DEFAULT_BASE_URL = 'https://api.productive.io/api/v2';

    /**
     * @param  list<string>  $featureFlags  Sent as the X-Feature-Flags header, e.g. filteringSkipDatetimeCastToDate.
     */
    public function __construct(
        public string $token,
        public string $organizationId,
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public int $maxAttempts = 3,
        public int $maxRetryAfter = 60,
        public bool $throttle = true,
        public ?string $cacheStore = null,
        public array $featureFlags = [],
    ) {
        $this->assertValid();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            token: self::parseString($config['token'] ?? null),
            organizationId: self::parseString($config['organization_id'] ?? null),
            baseUrl: self::parseString($config['base_url'] ?? null, self::DEFAULT_BASE_URL),
            timeout: self::parseInt($config['timeout'] ?? null, 30),
            connectTimeout: self::parseInt($config['connect_timeout'] ?? null, 10),
            maxAttempts: self::parseInt(self::section($config, 'retry')['max_attempts'] ?? null, 3),
            maxRetryAfter: self::parseInt(self::section($config, 'retry')['max_retry_after'] ?? null, 60),
            throttle: self::parseBool(self::section($config, 'throttle')['enabled'] ?? true),
            cacheStore: self::parseNullableString(self::section($config, 'throttle')['cache_store'] ?? null),
            featureFlags: self::parseList($config['feature_flags'] ?? []),
        );
    }

    public function withOrganization(string $organizationId): self
    {
        return $this->copy(['organizationId' => $organizationId]);
    }

    public function withToken(string $token): self
    {
        return $this->copy(['token' => $token]);
    }

    /**
     * The token is required for every call, the organization for all but the public endpoints.
     * Both are checked lazily, so the package can boot without credentials (e.g. in CI).
     */
    public function assertHasCredentials(bool $requiresOrganization = true): void
    {
        if ($this->token === '') {
            throw ConfigurationException::missing('token', 'PRODUCTIVE_API_TOKEN');
        }

        if ($requiresOrganization && $this->organizationId === '') {
            throw ConfigurationException::missing('organization_id', 'PRODUCTIVE_ORGANIZATION_ID');
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * True when the URL points at the configured API, so credentials are never sent elsewhere.
     */
    public function ownsUrl(string $url): bool
    {
        return str_starts_with($url, rtrim($this->baseUrl, '/') . '/');
    }

    /**
     * A stable, non-reversible identifier for the token, used to key client-side rate limit buckets.
     */
    public function tokenFingerprint(): string
    {
        return substr(hash('sha256', $this->token), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function copy(array $overrides): self
    {
        $values = array_merge(get_object_vars($this), $overrides);

        /** @phpstan-ignore argument.type (named arguments unpacked from this object's own properties) */
        return new self(...$values);
    }

    private function assertValid(): void
    {
        if (filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false || ! str_starts_with($this->baseUrl, 'https://')) {
            throw new ConfigurationException(sprintf('The Productive base URL must be an absolute https URL, "%s" given.', $this->baseUrl));
        }

        $this->assertValidLimits();
    }

    private function assertValidLimits(): void
    {
        if ($this->maxAttempts < 1) {
            throw new ConfigurationException('The Productive retry max_attempts must be at least 1.');
        }

        foreach ([$this->timeout, $this->connectTimeout, $this->maxRetryAfter] as $seconds) {
            if ($seconds < 0) {
                throw new ConfigurationException('Productive timeouts and max_retry_after cannot be negative.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<array-key, mixed>
     */
    private static function section(array $config, string $key): array
    {
        return is_array($config[$key] ?? null) ? $config[$key] : [];
    }

    private static function parseString(mixed $value, string $default = ''): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function parseNullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function parseInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private static function parseBool(mixed $value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $value;
    }

    /**
     * @return list<string>
     */
    private static function parseList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = array_map(fn(mixed $item): string => is_string($item) ? trim($item) : '', $value);

        return array_values(array_filter($items, fn(string $item): bool => $item !== ''));
    }
}
