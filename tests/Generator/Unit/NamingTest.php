<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Naming;

it('applies the naming rules', function () {
    expect(Naming::camel('move_dependent'))->toBe('moveDependent')
        ->and(Naming::camel('bulk-approve'))->toBe('bulkApprove')
        ->and(Naming::studly('reports/time_reports'))->toBe('ReportsTimeReports')
        ->and(Naming::studly('a.b'))->toBe('AB')
        ->and(Naming::modelName('time_entries'))->toBe('TimeEntry')
        ->and(Naming::modelName('people'))->toBe('Person');
});
