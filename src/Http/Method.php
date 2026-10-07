<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Patch = 'PATCH';
    case Put = 'PUT';
    case Delete = 'DELETE';

    /**
     * Only reads are retried after a server error or a dropped connection: repeating a write
     * that may already have been applied (send an invoice, approve time) is never safe.
     */
    public function isSafe(): bool
    {
        return $this === self::Get;
    }
}
