<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

/**
 * Marks an input field that was not given, so it is left out of the request.
 * This is what lets an update tell "leave unchanged" apart from "clear" (null).
 */
enum Undefined
{
    case Value;
}
