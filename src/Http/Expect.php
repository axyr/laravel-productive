<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

/**
 * What a successful response to a request is expected to contain.
 */
enum Expect: string
{
    case Resource = 'resource';
    case Collection = 'collection';
    case NoContent = 'no_content';
    case Binary = 'binary';
}
