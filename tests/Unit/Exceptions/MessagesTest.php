<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\ConfigurationException;
use Axyr\Productive\Exceptions\RelationshipNotIncludedException;

it('explains how to configure a missing value', function () {
    expect(ConfigurationException::missing('token', 'PRODUCTIVE_API_TOKEN')->getMessage())
        ->toBe('The Productive token is not configured. Set PRODUCTIVE_API_TOKEN or publish the config with `php artisan vendor:publish --tag=productive-config`.');
});

it('explains how to include a relationship', function () {
    expect(RelationshipNotIncludedException::for('tasks', 'assignee')->getMessage())
        ->toBe('The "assignee" relationship of this tasks resource was not included in the response. Add ->include(\'assignee\') to the query.');
});
