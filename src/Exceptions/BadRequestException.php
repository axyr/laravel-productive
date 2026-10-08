<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 400. A query parameter (filter, sort, group) is not supported by the endpoint.
 */
class BadRequestException extends ApiException {}
