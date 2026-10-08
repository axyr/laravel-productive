<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

/**
 * What a generated method returns.
 */
enum ResponseKind: string
{
    /** A model. */
    case Resource = 'resource';

    /** A model, or null when the endpoint answers without a body (the spec documents both). */
    case OptionalResource = 'optional_resource';

    /** A ModelCollection. */
    case Collection = 'collection';

    /** void. */
    case NoContent = 'no_content';

    /** The raw HTTP response, for downloads and redirects. */
    case Raw = 'raw';
}
