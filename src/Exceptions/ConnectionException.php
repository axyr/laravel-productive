<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * The request never produced an HTTP response: DNS, TLS, timeout or a dropped connection.
 */
class ConnectionException extends ProductiveException {}
