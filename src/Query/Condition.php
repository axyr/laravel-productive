<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

use Axyr\Productive\Exceptions\InvalidQueryException;
use BackedEnum;
use DateTimeInterface;

/**
 * A single filter condition. Values are normalized to their wire format on construction,
 * so an invalid value fails where it is written instead of when the request is sent.
 */
final readonly class Condition
{
    public string $value;

    /**
     * @param  bool  $explicitOperator  False when the operator was implied by where('field', $value).
     */
    public function __construct(
        public string $field,
        public Operator $operator,
        mixed $value,
        public bool $explicitOperator = true,
    ) {
        if (preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $field) !== 1) {
            throw new InvalidQueryException(sprintf('Invalid filter field "%s". Use letters, digits and underscores; separate nested keys with dots.', $field));
        }

        $this->value = self::normalize($value, $field);
    }

    /**
     * The field path as query string segments: "custom_fields.42" becomes "[custom_fields][42]".
     */
    public function path(): string
    {
        return '[' . str_replace('.', '][', $this->field) . ']';
    }

    private static function normalize(mixed $value, string $field): string
    {
        if (is_array($value)) {
            if ($value === []) {
                throw new InvalidQueryException(sprintf('The filter on "%s" was given an empty list.', $field));
            }

            return implode(',', array_map(fn(mixed $item): string => self::scalar($item, $field), $value));
        }

        return self::scalar($value, $field);
    }

    private static function scalar(mixed $value, string $field): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            default => throw new InvalidQueryException(sprintf(
                'The filter on "%s" was given a %s; use a string, number, boolean, enum, date or a list of those.',
                $field,
                get_debug_type($value),
            )),
        };
    }
}
