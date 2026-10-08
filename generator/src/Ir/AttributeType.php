<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

enum AttributeType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Date = 'date';
    case DateTime = 'date-time';
    case Time = 'time';
    case Object = 'object';
    case List = 'list';
    case Mixed = 'mixed';
}
