<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

/**
 * Productive's webhook signature: a `Productive-Signature` header of `t=<unix seconds>,s=<hex>`,
 * where `s` is HMAC-SHA256 over `<t>.<raw request body>` with the webhook's signature token.
 */
final readonly class WebhookSignature
{
    public const string HEADER = 'Productive-Signature';

    /**
     * @param  list<string>  $secrets  The signature tokens of the webhooks that may deliver here; any one of them verifies.
     * @param  int  $tolerance  How many seconds the timestamp may differ from now, against replays.
     */
    public function __construct(
        private array $secrets,
        private int $tolerance = 300,
    ) {}

    public function verify(?string $header, string $body, ?int $now = null): bool
    {
        $parts = self::parse($header);

        return $parts !== null && $this->isFresh($parts['timestamp'], $now ?? time()) && $this->matches($parts, $body);
    }

    private function isFresh(int $timestamp, int $now): bool
    {
        return abs($now - $timestamp) <= $this->tolerance;
    }

    /**
     * @param  array{timestamp: int, signature: string}  $parts
     */
    private function matches(array $parts, string $body): bool
    {
        foreach ($this->secrets as $secret) {
            if (hash_equals(self::digest($parts['timestamp'], $body, $secret), $parts['signature'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * A header value for a body, as Productive would sign it: for tests and simulations.
     */
    public static function sign(string $body, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't=' . $timestamp . ',s=' . self::digest($timestamp, $body, $secret);
    }

    /**
     * @return array{timestamp: int, signature: string}|null
     */
    private static function parse(?string $header): ?array
    {
        if ($header === null || preg_match('/^t=(\d+),s=([0-9a-fA-F]+)$/', trim($header), $match) !== 1) {
            return null;
        }

        return ['timestamp' => (int) $match[1], 'signature' => strtolower($match[2])];
    }

    private static function digest(int $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
