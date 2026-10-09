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
     * The example's attributes without "id" and "type": JSON:API forbids attributes with those
     * names, and a factory reads "id" as the resource ID. A few spec examples include them anyway.
     *
     * @return array<string, mixed>
     */
    public static function attributes(Model $model): array
    {
        return array_diff_key($model->example, ['id' => true, 'type' => true]);
    }
}
