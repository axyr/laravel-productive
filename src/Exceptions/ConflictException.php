<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 409. The resource is in a state that does not allow this action (e.g. rejecting an approved entry).
 */
class ConflictException extends ApiException {}
