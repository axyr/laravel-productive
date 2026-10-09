<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Blanks the string values of keys that look like secrets, at any depth, before a recording is written.
 * Metadata about a secret keeps its value and type: `token_expires_at`, `api_key_id`, `password_set`.
 */
final class Redactor
{
    public const string REDACTED = '[redacted]';

    private const string SECRET_KEY = '/token|secret|password|api_key|apikey|signature|private_key/i';

    /** Keys about a secret rather than holding one, e.g. token_expires_at or api_key_id. */
    private const string METADATA_KEY = '/(_at|_on|_date|_count|_id|_ids)$/i';

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
        return is_string($key)
            && is_string($value)
            && preg_match(self::SECRET_KEY, $key) === 1
            && preg_match(self::METADATA_KEY, $key) !== 1;
    }
}
