<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

enum ContentType: string
{
    case JsonApi = 'application/vnd.api+json';
    case JsonApiBulk = 'application/vnd.api+json; ext=bulk';
}
