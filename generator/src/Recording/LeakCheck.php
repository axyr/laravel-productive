<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Finds what must never reach a committed fixture: the organization ID, the token, the owner's
 * name and email, the organization's name (each word on its own, any case), and every value the
 * scrubber replaced.
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
     * Whole values only, and values only: "Budget" inside a key such as "enableBudgetViewSharing"
     * is no leak, nor is the key "manager" when a free-text value happened to read "manager".
     *
     * @param  list<string>  $replaced
     */
    private static function containsReplaced(string $output, array $replaced): bool
    {
        $long = array_filter($replaced, fn(string $original): bool => strlen($original) >= 4);
        $values = preg_replace('/^\s*"[^"\n]*":/m', '', $output) ?? $output;

        return self::contains($values, array_map(fn(string $original): string => '/(?<![\p{L}\p{N}])' . preg_quote($original, '/') . '(?![\p{L}\p{N}])/u', $long));
    }

    /**
     * The whole value, and each word of four or more characters on its own. For an email only the
     * words of the domain: a local part such as "info" also names harmless codes. An ID matches
     * as a whole number.
     *
     * @return array<string>
     */
    private static function patterns(string $value): array
    {
        if ($value === '') {
            return [];
        }

        // An ID as a whole number: 12345 must not match inside 1234567.
        if (ctype_digit($value)) {
            return ['/(?<!\d)' . $value . '(?!\d)/'];
        }

        return array_map(fn(string $part): string => '/' . preg_quote($part, '/') . '/i', [$value, ...self::words($value)]);
    }

    /**
     * @return array<string>  The words of four or more characters; of an email, those of its domain.
     */
    private static function words(string $value): array
    {
        $text = str_contains($value, '@') ? explode('@', $value, 2)[1] : $value;

        return array_filter(preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [], fn(string $word): bool => mb_strlen($word) >= 4);
    }
}
