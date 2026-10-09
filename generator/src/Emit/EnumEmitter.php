<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Naming;
use RuntimeException;

final class EnumEmitter
{
    public const NAMESPACE = 'Axyr\\Productive\\Enums';

    /**
     * Sort options: "-due_date" becomes DueDateDesc.
     *
     * @param  list<string>  $values
     */
    public static function sort(string $class, string $description, array $values): string
    {
        return self::emit($class, $description, $values, fn(string $value): string => Naming::studly($value) . (str_starts_with($value, '-') ? 'Desc' : ''));
    }

    /**
     * @param  list<string>  $values
     */
    public static function group(string $class, string $description, array $values): string
    {
        return self::emit($class, $description, $values, Naming::studly(...));
    }

    /**
     * @param  list<string>  $values
     * @param  callable(string): string  $caseName
     */
    private static function emit(string $class, string $description, array $values, callable $caseName): string
    {
        $cases = [];

        foreach ($values as $value) {
            $case = $caseName($value);

            if (isset($cases[$case])) {
                throw new RuntimeException(sprintf('%s: "%s" and "%s" both become case %s.', $class, $cases[$case], $value, $case));
            }

            $cases[$case] = $value;
        }

        $body = [
            ...PhpFile::docblock([$description]),
            'enum ' . $class . ': string',
            '{',
            ...array_map(fn(string $case, string $value): string => '    case ' . $case . ' = ' . Literal::string($value) . ';', array_keys($cases), $cases),
            '}',
        ];

        return PhpFile::render(self::NAMESPACE, [], $body);
    }
}
