<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 5xx. Reads are retried automatically before this is thrown; writes never are.
 */
class ServerException extends ApiException {}
