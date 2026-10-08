<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * Productive answered with a successful status but a body this SDK cannot interpret.
 */
class InvalidResponseException extends ProductiveException {}
