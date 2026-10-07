<?php

declare(strict_types=1);

namespace Axyr\Productive\Contracts;

use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\ConnectionException;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

interface ConnectorInterface
{
    /**
     * Send a request and return the successful response.
     *
     * @throws ApiException when Productive answers with an error status
     * @throws ConnectionException when no response was received
     */
    public function send(Request $request): Response;
}
