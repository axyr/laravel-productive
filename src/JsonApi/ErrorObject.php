<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

/**
 * A JSON:API error object.
 *
 * Productive puts extra keys next to the standard ones (e.g. `limit` and `period` on a 429);
 * everything that is not a standard member is kept in $meta.
 */
final readonly class ErrorObject
{
    private const STANDARD_MEMBERS = ['status', 'title', 'detail', 'code', 'source', 'id', 'links'];

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?int $status = null,
        public ?string $title = null,
        public ?string $detail = null,
        public ?string $code = null,
        public ?string $pointer = null,
        public ?string $parameter = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $error
     */
    public static function fromArray(array $error): self
    {
        $source = self::member($error, 'source');

        return new self(
            status: self::int($error['status'] ?? null),
            title: self::string($error['title'] ?? null),
            detail: self::string($error['detail'] ?? null),
            code: self::string($error['code'] ?? null),
            pointer: self::string($source['pointer'] ?? null),
            parameter: self::string($source['parameter'] ?? null),
            meta: self::meta($error),
        );
    }

    /**
     * The attribute a validation error points at: "/data/attributes/due_date" becomes "due_date".
     */
    public function attribute(): ?string
    {
        if ($this->pointer === null) {
            return null;
        }

        $segments = explode('/', trim($this->pointer, '/'));

        return end($segments) ?: null;
    }

    public function message(): string
    {
        return $this->detail ?? $this->title ?? 'Unknown error';
    }

    /**
     * @param  array<array-key, mixed>  $error
     * @return array<string, mixed>
     */
    private static function meta(array $error): array
    {
        $extra = array_diff_key($error, array_flip([...self::STANDARD_MEMBERS, 'meta']));

        /** @var array<string, mixed> */
        return [...self::member($error, 'meta'), ...$extra];
    }

    /**
     * @param  array<array-key, mixed>  $error
     * @return array<array-key, mixed>
     */
    private static function member(array $error, string $key): array
    {
        return is_array($error[$key] ?? null) ? $error[$key] : [];
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function string(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return is_int($value) ? (string) $value : null;
    }
}
