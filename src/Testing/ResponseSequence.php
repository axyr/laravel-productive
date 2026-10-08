<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Exceptions\StrayRequestException;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

final class ResponseSequence
{
    /** @var array<Response> */
    private array $responses;

    public function __construct(Response ...$responses)
    {
        $this->responses = $responses;
    }

    public function next(Request $request): Response
    {
        return array_shift($this->responses)
            ?? throw new StrayRequestException(sprintf('The response sequence for [%s] is exhausted.', $request->describe()));
    }

    public function isEmpty(): bool
    {
        return $this->responses === [];
    }
}
