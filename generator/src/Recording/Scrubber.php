<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Replaces what could identify someone in recorded resources, and keeps what tests need: every
 * key, every JSON type, numbers, booleans, dates, and short lowercase values such as statuses
 * (but not hashes or UUIDs).
 * IDs are renumbered consistently wherever they occur, so relationships still connect.
 */
final class Scrubber
{
    /** Keys whose text is always replaced, even when it looks like a harmless code ("acme"). */
    private const string IDENTITY_KEY = '/(name|names|title|email|emails|key|slug|domain|uuid|url|login|handle|phone|address|description|body|note|notes|comment|message|text|content)$/';

    /** Empty, numbers, dates and times, currency codes and colours carry no identity. */
    private const array KEPT = [
        '/^$/',
        '/^-?\d+(\.\d+)?$/',
        '/^\d{4}-\d{2}-\d{2}([T ][\d:.+\-Z]+)?$/',
        '/^\d{1,2}:\d{2}(:\d{2})?$/',
        '/^[A-Z]{3}$/',
        '/^#[0-9a-fA-F]{3,8}$/',
    ];

    /** @var array<array-key, int> New IDs by original; PHP keys a numeric ID by int. */
    private array $ids = [];

    /** @var array<string, string> Replacements by original value. */
    private array $strings = [];

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public function scrub(array $value): array
    {
        $scrubbed = [];

        foreach ($value as $key => $item) {
            $scrubbed[$key] = is_string($key) ? $this->keyed($key, $item) : $this->value($item);
        }

        return $scrubbed;
    }

    /**
     * @return list<string>  Every original string that was replaced.
     */
    public function replaced(): array
    {
        return array_map(strval(...), array_keys($this->strings));
    }

    private function keyed(string $key, mixed $item): mixed
    {
        return match (true) {
            $key === 'id' || str_ends_with($key, '_id') => $this->renumber($item),
            str_ends_with($key, '_ids') => is_array($item) ? array_map($this->renumber(...), $item) : $this->value($item),
            default => $this->field($key, $item),
        };
    }

    private function field(string $key, mixed $item): mixed
    {
        return match (true) {
            str_ends_with($key, 'custom_fields') && self::isMap($item) => $this->customFields($item),
            is_string($item) && preg_match(self::IDENTITY_KEY, $key) === 1 => $this->identity($item),
            default => $this->value($item),
        };
    }

    /**
     * @phpstan-assert-if-true array<array-key, mixed> $value
     */
    private static function isMap(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    private function identity(string $value): string
    {
        return $value === '' ? '' : $this->replace($value);
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<array-key, mixed>
     */
    private function customFields(array $fields): array
    {
        $scrubbed = [];

        foreach ($fields as $key => $value) {
            $scrubbed[$this->fieldKey($key)] = $this->value($value);
        }

        return $scrubbed;
    }

    /**
     * Custom field values are keyed by the custom field's ID.
     */
    private function fieldKey(int|string $key): string
    {
        return is_string($key) && ! ctype_digit($key) ? $this->string($key) : (string) $this->number($key);
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => $this->scrub($value),
            is_string($value) => $this->string($value),
            default => $value,
        };
    }

    /**
     * An ID keeps its JSON type: an int stays an int, a string a string.
     */
    private function renumber(mixed $id): mixed
    {
        if (! self::isNumericId($id)) {
            return $this->value($id);
        }

        return is_int($id) ? $this->number($id) : (string) $this->number($id);
    }

    /**
     * The new number for an original ID, the same every time it occurs.
     */
    private function number(int|string $id): int
    {
        return $this->ids[$id] ??= 1000 + count($this->ids) + 1;
    }

    /**
     * @phpstan-assert-if-true int|numeric-string $id
     */
    private static function isNumericId(mixed $id): bool
    {
        return is_int($id) || (is_string($id) && ctype_digit($id));
    }

    private function string(string $value): string
    {
        return self::isKept($value) ? $value : $this->replace($value);
    }

    private function replace(string $value): string
    {
        return $this->strings[$value] ??= match (true) {
            filter_var($value, FILTER_VALIDATE_EMAIL) !== false => 'person-' . (count($this->strings) + 1) . '@example.test',
            preg_match('#^https?://#', $value) === 1 => 'https://example.test/' . (count($this->strings) + 1),
            default => 'Text ' . (count($this->strings) + 1),
        };
    }

    /**
     * Values that carry no identity: see KEPT, plus short lowercase codes such as statuses.
     */
    private static function isKept(string $value): bool
    {
        foreach (self::KEPT as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return preg_match('/^[a-z][a-z0-9_\-.]{0,39}$/', $value) === 1 && ! self::isToken($value);
    }

    /**
     * Hashes, keys and UUIDs (a run of hex digits) look like lowercase codes but can grant access,
     * such as an email-in address or a public share link.
     */
    private static function isToken(string $value): bool
    {
        return preg_match('/[0-9a-f]{12,}/', str_replace('-', '', $value)) === 1;
    }
}
