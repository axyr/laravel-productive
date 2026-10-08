<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

use RuntimeException;

/**
 * Base class for every exception this package throws, so callers can catch them all at once.
 */
class ProductiveException extends RuntimeException {}
