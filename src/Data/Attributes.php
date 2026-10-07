<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

use BackedEnum;
use DateTimeImmutable;
use Throwable;

/**
 * Reads typed values from raw JSON:API attributes.
 *
 * Reading is lenient on purpose: a value is converted when that is lossless and becomes null
 * when it is not, so an API-side type change never breaks hydration. The untouched value is
 * always available through Model::attribute().
 */
final readonly class Attributes
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function mixed(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->mixed($key);

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }

    public function int(string $key): ?int
    {
        $value = $this->mixed($key);

        return match (true) {
            is_int($value) => $value,
            is_float($value) && $value === (float) (int) $value => (int) $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => null,
        };
    }

    public function float(string $key): ?float
    {
        $value = $this->mixed($key);

        return is_numeric($value) ? (float) $value : null;
    }

    public function bool(string $key): ?bool
    {
        $value = $this->mixed($key);

        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }

    /**
     * A calendar date ("2026-03-31"), at midnight in the default timezone.
     */
    public function date(string $key): ?DateTimeImmutable
    {
        $value = $this->string($key);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function dateTime(string $key): ?DateTimeImmutable
    {
        $value = $this->string($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A JSON object, e.g. custom field values keyed by custom field ID.
     *
     * @return array<array-key, mixed>|null
     */
    public function object(string $key): ?array
    {
        $value = $this->mixed($key);

        return is_array($value) ? $value : null;
    }

    /**
     * A JSON array. An object where a list is expected is not a list, and reads as null.
     *
     * @return list<mixed>|null
     */
    public function list(string $key): ?array
    {
        $value = $this->mixed($key);

        return is_array($value) && array_is_list($value) ? $value : null;
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    public function enum(string $key, string $enum): ?BackedEnum
    {
        $value = $this->mixed($key);

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $matches = array_filter($enum::cases(), fn(BackedEnum $case): bool => (string) $case->value === (string) $value);

        return reset($matches) ?: null;
    }
}
