<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use DateTimeImmutable;

/**
 * Turns one webhook delivery into a recording: the request as it arrived, the raw body exactly as
 * sent (a signature is computed over it), the decoded body redacted, and, when the webhook's
 * signature token is known, whether the documented scheme verifies it: HMAC-SHA256 over
 * "<timestamp>.<raw body>", carried as "t=<seconds>,s=<signature>" in the Productive-Signature
 * header. The signature itself is kept: without the token it reveals nothing, and the fixtures
 * need it to test verification.
 */
final readonly class WebhookCapture
{
    public const string SIGNATURE_HEADER = 'Productive-Signature';

    /** Headers that would carry a credential of the receiver, blanked before anything is written. */
    private const array BLANKED = ['authorization', 'cookie', 'proxy-authorization', 'x-auth-token'];

    /**
     * @param  list<string>  $secrets  The signature tokens of the webhooks that deliver here: each webhook has its own.
     */
    public function __construct(private array $secrets = []) {}

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function record(DateTimeImmutable $receivedAt, string $method, string $uri, array $headers, string $body, int $respondedWith): array
    {
        return [
            'received_at' => $receivedAt->format(DATE_ATOM),
            'request' => ['method' => $method, 'uri' => $uri],
            'headers' => self::headers($headers),
            'body' => $body,
            'json' => Redactor::redact(json_decode($body, true)),
            'signature' => $this->signature($headers, $body),
            'responded_with' => $respondedWith,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private static function headers(array $headers): array
    {
        $kept = [];

        foreach ($headers as $name => $value) {
            $kept[$name] = in_array(strtolower($name), self::BLANKED, true) ? Redactor::REDACTED : $value;
        }

        ksort($kept);

        return $kept;
    }

    /**
     * The parsed signature header, and which encoding of the documented scheme matches it when
     * the token is known: "hex", "base64" or false.
     *
     * @param  array<string, string>  $headers
     * @return array{timestamp: string, signature: string, valid: string|false|null}|null
     */
    private function signature(array $headers, string $body): ?array
    {
        $parts = self::parts(self::header($headers, self::SIGNATURE_HEADER));

        if (! isset($parts['t'], $parts['s'])) {
            return null;
        }

        return ['timestamp' => $parts['t'], 'signature' => $parts['s'], 'valid' => $this->verify($parts['t'], $parts['s'], $body)];
    }

    /**
     * "t=1700000000,s=abc" as ["t" => "1700000000", "s" => "abc"].
     *
     * @return array<string, string>
     */
    private static function parts(?string $header): array
    {
        $parts = [];

        foreach (explode(',', (string) $header) as $part) {
            [$key, $value] = [...explode('=', trim($part), 2), ''];
            $parts[$key] = $value;
        }

        return $parts;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The encoding that verifies with one of the tokens; false when none does, null without tokens.
     */
    private function verify(string $timestamp, string $signature, string $body): string|false|null
    {
        if ($this->secrets === []) {
            return null;
        }

        foreach ($this->secrets as $secret) {
            $encoding = self::encoding(hash_hmac('sha256', $timestamp . '.' . $body, $secret, true), $signature);

            if ($encoding !== null) {
                return $encoding;
            }
        }

        return false;
    }

    private static function encoding(string $digest, string $signature): ?string
    {
        return match (true) {
            hash_equals(bin2hex($digest), $signature) => 'hex',
            hash_equals(base64_encode($digest), $signature) => 'base64',
            default => null,
        };
    }
}
