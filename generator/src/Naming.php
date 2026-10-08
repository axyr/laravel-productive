<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Illuminate\Support\Str;

/**
 * The naming rules every generated class, method and property follows.
 */
final class Naming
{
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-', '.', '/'], ' ', $value)));
    }

    /**
     * "time_entries" becomes "TimeEntry", "people" becomes "Person".
     */
    public static function modelName(string $plural): string
    {
        return self::studly(Str::singular($plural));
    }
}
