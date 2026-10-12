<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Model;

final class FactoryEmitter
{
    public const NAMESPACE = 'Axyr\\Productive\\Testing\\Factories';

    public static function emit(Model $model): string
    {
        $body = [
            ...PhpFile::docblock(['Defaults are the attributes of the example in Productive\'s API reference.', '', '@extends Factory<' . $model->class . '>', '', '@SuppressWarnings("PHPMD.ExcessiveMethodLength")']),
            'final class ' . $model->class . 'Factory extends Factory',
            '{',
            '    public static function new(): self',
            '    {',
            '        return new self();',
            '    }',
            '',
            '    protected function model(): string',
            '    {',
            '        return ' . $model->class . '::class;',
            '    }',
            '',
            '    protected function definition(): array',
            '    {',
            '        return [',
            ...self::definition($model),
            '        ];',
            '    }',
            '}',
        ];

        return PhpFile::render(self::NAMESPACE, [ModelEmitter::NAMESPACE . '\\' . $model->class], $body);
    }

    /**
     * @return list<string>
     */
    private static function definition(Model $model): array
    {
        $example = self::attributes($model);

        return array_map(
            fn(int|string $key, mixed $value): string => '            ' . Literal::export($key) . ' => ' . Literal::export($value) . ',',
            array_keys($example),
            $example,
        );
    }

    /**
     * The example's attributes without "id", which a factory reads as the resource ID, and without a
     * "type" that only repeats the resource type. A real "type" attribute (a contact entry's "main")
     * stays: the model reads it as typeValue.
     *
     * @return array<string, mixed>
     */
    public static function attributes(Model $model): array
    {
        $attributes = array_diff_key($model->example, ['id' => null]);

        return ($attributes['type'] ?? null) === $model->type ? array_diff_key($attributes, ['type' => null]) : $attributes;
    }
}
