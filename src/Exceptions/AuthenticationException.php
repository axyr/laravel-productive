<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 401. The API token is missing, invalid or revoked.
 */
class AuthenticationException extends ApiException {}
