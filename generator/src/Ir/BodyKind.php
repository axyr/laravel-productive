<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

/**
 * What an operation sends as its request body.
 */
enum BodyKind: string
{
    /** No body. */
    case None = 'none';

    /** A JSON:API document built from a typed input object. */
    case Attributes = 'attributes';

    /** A JSON:API document built from an attribute array: the spec documents no attributes for this create or update. */
    case Data = 'data';

    /** A plain JSON object (not JSON:API) built from a typed input object, e.g. {"markdown": "…"}. */
    case Plain = 'plain';
}
