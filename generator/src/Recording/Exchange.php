<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Http\Response;

/**
 * The part of a recording a response gives: its status, the headers worth keeping and the body,
 * decoded and redacted. The recorder and the write recorder store responses in this one shape.
 */
final class Exchange
{
    private const array HEADERS = ['Content-Type', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

    /**
     * @return array{status: int, headers: array<string, string>, body: mixed}
     */
    public static function response(Response $response): array
    {
        $headers = [];

        foreach (self::HEADERS as $name) {
            $value = $response->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return ['status' => $response->status, 'headers' => $headers, 'body' => Redactor::redact(Json::decode($response->body))];
    }
}
