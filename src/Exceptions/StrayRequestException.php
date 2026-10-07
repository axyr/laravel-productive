<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * Thrown by the testing fake when a request has no seeded response and stray requests are prevented.
 */
class StrayRequestException extends ProductiveException {}
