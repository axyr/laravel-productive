<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\Input;

final class InputEmitter
{
    public const NAMESPACE = 'Axyr\\Productive\\Data\\Input';

    public static function emit(Input $input, string $summary): string
    {
        $types = array_map(Types::input(...), $input->attributes);
        $needsUndefined = array_filter($input->attributes, fn(Attribute $attribute): bool => ! $attribute->required) !== [];
        $needsDate = array_filter($types, fn(array $type): bool => str_contains($type['type'], 'DateTimeInterface')) !== [];
        $imports = ['Axyr\\Productive\\Data\\InputData', ...($needsUndefined ? ['Axyr\\Productive\\Data\\Undefined'] : []), ...($needsDate ? ['DateTimeInterface'] : [])];
        $long = count($input->attributes) >= 10 ? ['', '@SuppressWarnings("PHPMD.ExcessiveParameterList")', '@SuppressWarnings("PHPMD.ExcessiveMethodLength")'] : [];

        $body = [
            ...PhpFile::docblock([$summary, ...$long]),
            'final readonly class ' . $input->class . ' extends InputData',
            '{',
            ...PhpFile::docblock(self::parameterDocs($input), '    '),
            '    public function __construct(',
            ...array_map(self::parameter(...), $input->attributes),
            '    ) {}',
            '',
            '    public function toAttributes(): array',
            '    {',
            '        return self::filter([',
            ...array_map(self::mapping(...), $input->attributes),
            '        ]);',
            '    }',
            '}',
        ];

        return PhpFile::render(self::NAMESPACE, $imports, $body);
    }

    /**
     * Trailing spaces from an empty description are trimmed by PhpFile::docblock().
     *
     * @return list<string>
     */
    private static function parameterDocs(Input $input): array
    {
        $docs = [];

        foreach ($input->attributes as $attribute) {
            $doc = Types::input($attribute)['doc'];

            if ($doc !== null) {
                $docs[] = sprintf('@param  %s%s  $%s  %s', $doc, $attribute->required ? '' : '|Undefined|null', $attribute->property, $attribute->description);
            }
        }

        return $docs;
    }

    private static function parameter(Attribute $attribute): string
    {
        $type = Types::input($attribute)['type'];

        if ($attribute->required) {
            return '        public ' . $type . ' $' . $attribute->property . ',';
        }

        return '        public ' . ($type === 'mixed' ? 'mixed' : $type . '|Undefined|null') . ' $' . $attribute->property . ' = Undefined::Value,';
    }

    private static function mapping(Attribute $attribute): string
    {
        $format = Types::input($attribute)['format'];
        $value = '$this->' . $attribute->property;

        return '            ' . Literal::string($attribute->name) . ' => ' . ($format === null ? $value : 'self::' . $format . '(' . $value . ')') . ',';
    }
}
