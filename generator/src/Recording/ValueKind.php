<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Generator\Ir\AttributeType;

/**
 * The kind of a recorded JSON value: fine enough to decide which lenient reader can read it,
 * without keeping the value itself.
 */
enum ValueKind: string
{
    case Null = 'null';
    case Bool = 'bool';
    case Int = 'int';
    case Float = 'float';
    case IntegerString = 'integer-string';
    case DecimalString = 'decimal-string';
    case Date = 'date';
    case DateTime = 'date-time';
    case String = 'string';
    case Empty = 'empty';
    case List = 'list';
    case Object = 'object';

    public static function detect(mixed $value): self
    {
        return match (true) {
            $value === null => self::Null,
            is_bool($value) => self::Bool,
            is_int($value) => self::Int,
            is_float($value) => self::Float,
            is_string($value) => self::ofString($value),
            // Decoded JSON cannot tell [] from {}: an empty array says nothing about the shape.
            $value === [] => self::Empty,
            is_array($value) && array_is_list($value) => self::List,
            default => self::Object,
        };
    }

    private static function ofString(string $value): self
    {
        return match (true) {
            preg_match('/^-?\d+$/', $value) === 1 => self::IntegerString,
            preg_match('/^-?\d+\.\d+$/', $value) === 1 => self::DecimalString,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 => self::Date,
            preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value) === 1 => self::DateTime,
            default => self::String,
        };
    }

    /**
     * A value of this kind, to test whether a reader can read it. Null and empty carry no shape.
     */
    public function sample(): mixed
    {
        return match ($this) {
            self::Null => null,
            self::Bool => true,
            // 7, not 1: a boolean reader must not pass for an integer field.
            self::Int => 7,
            self::Float => 7.5,
            self::IntegerString => '7',
            self::DecimalString => '7.50',
            self::Date => '2026-01-02',
            self::DateTime => '2026-01-02T03:04:05.000+01:00',
            self::String => 'text',
            self::Empty => [],
            self::List => ['a'],
            self::Object => ['a' => 1],
        };
    }

    /**
     * Whether a value of this kind says anything about the type.
     */
    public function isInformative(): bool
    {
        return $this !== self::Null && $this !== self::Empty;
    }

    /**
     * The attribute type that fits every informative kind, or null when none was seen.
     *
     * @param  array<self>  $kinds
     */
    public static function typeOf(array $kinds): ?AttributeType
    {
        $kinds = array_filter($kinds, fn(self $kind): bool => $kind->isInformative());

        return $kinds === [] ? null : self::firstMatching($kinds);
    }

    /**
     * @param  non-empty-array<self>  $kinds
     */
    private static function firstMatching(array $kinds): AttributeType
    {
        $within = fn(self ...$allowed): bool => array_diff(array_column($kinds, 'value'), array_column($allowed, 'value')) === [];

        return match (true) {
            $within(self::Bool) => AttributeType::Bool,
            $within(self::Int) => AttributeType::Int,
            $within(self::Int, self::Float) => AttributeType::Float,
            $within(self::Date) => AttributeType::Date,
            $within(self::Date, self::DateTime) => AttributeType::DateTime,
            $within(self::String, self::IntegerString, self::DecimalString, self::Date, self::DateTime) => AttributeType::String,
            $within(self::List) => AttributeType::List,
            $within(self::Object) => AttributeType::Object,
            default => AttributeType::Mixed,
        };
    }
}
