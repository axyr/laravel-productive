<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Blanks the values of keys that look like secrets, at any depth, before a recording is written.
 */
final class Redactor
{
    public const string REDACTED = '[redacted]';

    private const string SECRET_KEY = '/token|secret|password|api_key|apikey|signature|private_key/i';

    public static function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            $redacted[$key] = self::isSecret($key, $item) ? self::REDACTED : self::redact($item);
        }

        return $redacted;
    }

    private static function isSecret(int|string $key, mixed $value): bool
    {
        return is_string($key) && $value !== null && preg_match(self::SECRET_KEY, $key) === 1;
    }
}
