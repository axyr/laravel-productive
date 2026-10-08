<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * A query was built that Productive would reject, caught before any request is sent.
 */
class InvalidQueryException extends ProductiveException {}
