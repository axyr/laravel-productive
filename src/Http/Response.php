<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

use Axyr\Productive\Exceptions\InvalidResponseException;
use JsonException;

/**
 * A raw HTTP response, decoupled from the HTTP client that produced it.
 */
final readonly class Response
{
    /** @var array<string, list<string>> */
    public array $headers;

    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        public int $status,
        array $headers = [],
        public string $body = '',
    ) {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = is_array($values) ? array_values($values) : [$values];
        }

        $this->headers = $normalized;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function isEmpty(): bool
    {
        return trim($this->body) === '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidResponseException
     */
    public function json(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        try {
            $decoded = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException(sprintf('Productive returned invalid JSON (HTTP %d): %s', $this->status, $exception->getMessage()), previous: $exception);
        }

        return self::object($decoded) ?? throw new InvalidResponseException(sprintf('Productive returned a JSON body that is not an object (HTTP %d).', $this->status));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function object(mixed $decoded): ?array
    {
        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
