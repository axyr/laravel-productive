<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

/**
 * The tables of the generated docs.
 */
final class Markdown
{
    /**
     * A section with a table, or nothing without rows.
     *
     * @param  list<string>  $headers
     * @param  array<list<string>>  $rows
     * @return list<string>
     */
    public static function section(string $title, string $description, array $headers, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $lines = ['', '## ' . $title, '', $description, '', '| ' . implode(' | ', $headers) . ' |', '|' . str_repeat(' --- |', count($headers))];

        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        return $lines;
    }

    /**
     * @param  list<string>  $names
     */
    public static function codes(array $names): string
    {
        return implode(', ', array_map(fn(string $name): string => '`' . $name . '`', $names));
    }
}
