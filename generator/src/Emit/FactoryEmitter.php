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
        return array_map(
            fn(int|string $key, mixed $value): string => '            ' . Literal::export($key) . ' => ' . Literal::export($value) . ',',
            array_keys($model->example),
            $model->example,
        );
    }
}
