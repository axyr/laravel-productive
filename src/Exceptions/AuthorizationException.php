<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 403. The token's user lacks permission for this resource.
 */
class AuthorizationException extends ApiException {}
