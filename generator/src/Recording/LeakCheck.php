<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Finds what must never reach a committed fixture: the organization ID, the token, the owner's
 * name and email (each word on its own, any case), and every value the scrubber replaced.
 */
final class LeakCheck
{
    /**
     * @param  array<string, string>  $terms  Secret values by label, e.g. ["organization ID" => "12345"].
     * @param  list<string>  $replaced  Original values the scrubber replaced.
     * @return list<string>  The labels of whatever leaked, never the values themselves.
     */
    public static function leaks(string $output, array $terms, array $replaced): array
    {
        $leaks = array_keys(array_filter($terms, fn(string $value): bool => self::contains($output, self::patterns($value))));

        return self::containsReplaced($output, $replaced) ? [...$leaks, 'a replaced value'] : $leaks;
    }

    /**
     * @param  array<string>  $patterns
     */
    private static function contains(string $output, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whole values only: "Budget" inside a key such as "enableBudgetViewSharing" is no leak.
     *
     * @param  list<string>  $replaced
     */
    private static function containsReplaced(string $output, array $replaced): bool
    {
        $long = array_filter($replaced, fn(string $original): bool => strlen($original) >= 4);

        return self::contains($output, array_map(fn(string $original): string => '/(?<![\p{L}\p{N}])' . preg_quote($original, '/') . '(?![\p{L}\p{N}])/u', $long));
    }

    /**
     * @return array<string>
     */
    private static function patterns(string $value): array
    {
        if (ctype_digit($value)) {
            return ['/(?<!\d)' . $value . '(?!\d)/'];
        }

        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $value) ?: [], fn(string $word): bool => mb_strlen($word) >= 4);
        $quoted = array_map(fn(string $part): string => '/' . preg_quote($part, '/') . '/i', [$value, ...$words]);

        return $value === '' ? [] : $quoted;
    }
}
