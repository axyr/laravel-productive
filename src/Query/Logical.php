<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

enum Logical: string
{
    case And = 'and';
    case Or = 'or';
}
