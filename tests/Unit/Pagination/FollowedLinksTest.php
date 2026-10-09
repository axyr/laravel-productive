<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Pagination\FollowedLinks;

it('accepts each link once', function () {
    $links = new FollowedLinks('tasks.index');

    $links->follow('https://api.productive.test/api/v2/tasks?page[after]=a');
    $links->follow('https://api.productive.test/api/v2/tasks?page[after]=b');

    expect(fn() => $links->follow('https://api.productive.test/api/v2/tasks?page[after]=a'))
        ->toThrow(InvalidResponseException::class, 'Productive returned the next page link "https://api.productive.test/api/v2/tasks?page[after]=a" twice [tasks.index]; stopping instead of looping.');
});
