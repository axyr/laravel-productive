<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\OperationKind;

/**
 * Example request values for generated contract tests.
 */
final class Samples
{
    /**
     * Required attributes for creates and actions; one attribute for updates, whose fields are all optional.
     *
     * @return array<string, mixed>
     */
    public static function attributes(?Input $input, OperationKind $kind): array
    {
        if ($input === null) {
            return ['name' => 'Example'];
        }

        $values = [];

        foreach (self::chosen($input, $kind) as $attribute) {
            $values[$attribute->name] = self::value($attribute);
        }

        return $values;
    }

    /**
     * @return array<Attribute>
     */
    private static function chosen(Input $input, OperationKind $kind): array
    {
        $required = array_filter($input->attributes, fn(Attribute $attribute): bool => $attribute->required);
        $partial = $required === [] || in_array($kind, [OperationKind::Update, OperationKind::UpdateBulk], true);

        return $partial ? array_slice($input->attributes, 0, 1) : $required;
    }

    /**
     * A sample list item or map value of the given scalar type; a string when the spec does not say.
     */
    private static function scalar(?AttributeType $type): int|float|bool|string
    {
        return match ($type) {
            AttributeType::Int => 1,
            AttributeType::Float => 1.5,
            AttributeType::Bool => true,
            default => 'value',
        };
    }

    public static function value(Attribute $attribute): mixed
    {
        if ($attribute->enum !== []) {
            return $attribute->enum[0];
        }

        return match ($attribute->type) {
            AttributeType::Int => 1,
            AttributeType::Float => 1.5,
            AttributeType::Bool => true,
            AttributeType::Date => '2026-01-15',
            AttributeType::DateTime => '2026-01-15T09:00:00+00:00',
            AttributeType::Time => '09:00',
            AttributeType::Object => ['1' => self::scalar($attribute->items)],
            AttributeType::List => [self::scalar($attribute->items)],
            AttributeType::String, AttributeType::Mixed => 'Example',
        };
    }
}
