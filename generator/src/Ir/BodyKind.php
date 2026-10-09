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

    /** One typed input sent as a bulk document (`ext=bulk`), for actions that only accept that format (deals/copy). */
    case BulkItem = 'bulk_item';

    /**
     * An optional attribute array, for actions the spec documents without a body that cannot work
     * without one: POST actions (mostly copies) and collection writes (merges). Nothing is sent
     * when it is empty.
     */
    case OptionalData = 'optional_data';
}
