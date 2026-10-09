<?php

declare(strict_types=1);

use Axyr\Productive\ProductiveFacade as Productive;
use Illuminate\Http\Client\StrayRequestException;

it('refuses to send a request that no fake response matches', function () {
    Productive::tasks()->find(1);
})->throws(StrayRequestException::class);
