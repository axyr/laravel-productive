<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\Recording\WritePlan;
use Axyr\Productive\Generator\Recording\WriteStep;

function writeOperation(string $key, string $method, string $path): Operation
{
    $kind = match (substr($key, strrpos($key, '.') + 1)) {
        'create' => OperationKind::Create,
        'update' => OperationKind::Update,
        'destroy' => OperationKind::Destroy,
        default => OperationKind::Action,
    };

    return new Operation($key, 'x', $kind, $method, $path, str_contains($path, '{id}') ? ['id'] : [], ResponseKind::Resource, false, true, $key);
}

function writeApi(): Api
{
    $resource = fn(string $path, string $type, array $actions): Resource => new Resource($path, 'X', 'X', $type, null, array_map(
        fn(string $action): Operation => match ($action) {
            'create' => writeOperation($path . '.create', 'POST', $path),
            'update' => writeOperation($path . '.update', 'PATCH', $path . '/{id}'),
            'destroy' => writeOperation($path . '.destroy', 'DELETE', $path . '/{id}'),
            'merge' => writeOperation($path . '.merge', 'PATCH', $path . '/merge'),
            'copy' => writeOperation($path . '.copy', 'POST', $path . '/copy'),
            default => writeOperation($path . '.' . $action, 'PATCH', $path . '/{id}/' . $action),
        },
        $actions,
    ));

    return new Api([
        $resource('holiday_calendars', 'holiday_calendars', ['create', 'update', 'destroy', 'archive', 'copy']),
        $resource('holidays', 'holidays', ['create', 'update', 'destroy']),
        $resource('tax_rates', 'tax_rates', ['create', 'archive', 'restore', 'send', 'merge']),
        $resource('invitations', 'invitations', ['create']),
    ], []);
}

/**
 * @param  list<WriteStep>  $steps
 * @return list<string>
 */
function stepKeys(array $steps): array
{
    return array_map(fn(WriteStep $step): string => $step->operation->key . ($step->cleanup ? ' (cleanup)' : ''), $steps);
}

it('plans each recipe as a create, an update and its actions, then cleans up in reverse order', function () {
    $steps = WritePlan::steps(writeApi(), [
        'holiday_calendars' => ['tier' => 1, 'create' => ['name' => 'SDK check'], 'update' => ['name' => 'SDK check 2']],
        'holidays' => ['tier' => 1, 'create' => ['holiday_calendar_id' => '{created:holiday_calendars}']],
        'tax_rates' => ['tier' => 1, 'create' => ['name' => 'SDK check'], 'actions' => ['restore' => null, 'merge' => ['ids' => [1]], 0 => 'ignored'], 'cleanup' => 'archive'],
    ]);

    expect(stepKeys($steps))->toBe([
        'holiday_calendars.create', 'holiday_calendars.update',
        'holidays.create',
        'tax_rates.create', 'tax_rates.restore', 'tax_rates.merge',
        'tax_rates.archive (cleanup)', 'holidays.destroy (cleanup)', 'holiday_calendars.destroy (cleanup)',
    ])
        ->and($steps[0])->toEqual(new WriteStep(writeOperation('holiday_calendars.create', 'POST', 'holiday_calendars'), 'holiday_calendars', 'holiday_calendars', ['name' => 'SDK check'], creates: true))
        ->and($steps[1]->onCreated)->toBeTrue()
        ->and($steps[1]->creates)->toBeFalse()
        ->and($steps[4]->attributes)->toBeNull()
        ->and($steps[4]->onCreated)->toBeTrue()
        ->and($steps[5]->attributes)->toBe(['ids' => [1]])
        ->and($steps[5]->onCreated)->toBeFalse()
        ->and($steps[6]->attributes)->toBeNull()
        ->and($steps[6]->onCreated)->toBeTrue();
});

it('runs a cleanup of several steps in their order, such as archive before destroy', function () {
    $steps = WritePlan::steps(writeApi(), [
        'holiday_calendars' => ['create' => ['name' => 'SDK check'], 'cleanup' => ['archive', 'none', 'destroy']],
        'holidays' => ['create' => ['name' => 'SDK check'], 'cleanup' => ['none']],
    ]);

    expect(stepKeys($steps))->toBe(['holiday_calendars.create', 'holidays.create', 'holiday_calendars.archive (cleanup)', 'holiday_calendars.destroy (cleanup)']);
});

it('makes a second record of a resource under a label, with the create or another operation', function () {
    $steps = WritePlan::steps(writeApi(), [
        'holiday_calendars' => ['create' => ['name' => 'SDK check']],
        'holiday_calendars#copy' => ['create_with' => 'copy', 'create' => ['template_id' => '{created:holiday_calendars}'], 'update' => ['name' => 'SDK check copy']],
        'holiday_calendars#second' => ['create_with' => 5, 'create' => ['name' => 'SDK check 2'], 'actions' => ['destroy' => ['target_id' => '{created:holiday_calendars}']], 'cleanup' => 'none'],
    ]);

    expect(stepKeys($steps))->toBe([
        'holiday_calendars.create', 'holiday_calendars.copy', 'holiday_calendars.update', 'holiday_calendars.create', 'holiday_calendars.destroy',
        'holiday_calendars.destroy (cleanup)', 'holiday_calendars.destroy (cleanup)',
    ])
        ->and(array_map(fn(WriteStep $step): string => $step->resource, $steps))->toBe([
            'holiday_calendars', 'holiday_calendars#copy', 'holiday_calendars#copy', 'holiday_calendars#second', 'holiday_calendars#second',
            'holiday_calendars#copy', 'holiday_calendars',
        ])
        ->and($steps[1]->creates)->toBeTrue()
        ->and($steps[1]->onCreated)->toBeFalse()
        ->and($steps[1]->attributes)->toBe(['template_id' => '{created:holiday_calendars}'])
        ->and($steps[1]->type)->toBe('holiday_calendars')
        ->and($steps[4]->onCreated)->toBeTrue()
        ->and($steps[4]->attributes)->toBe(['target_id' => '{created:holiday_calendars}']);
});

it('plans one tier or one resource, and leaves out a cleanup of none', function () {
    $recipes = [
        'holiday_calendars' => ['tier' => 1, 'create' => ['name' => 'SDK check'], 'cleanup' => 'none'],
        'holidays' => ['tier' => 2, 'create' => ['name' => 'SDK check']],
    ];

    expect(stepKeys(WritePlan::steps(writeApi(), $recipes, tier: 1)))->toBe(['holiday_calendars.create'])
        ->and(stepKeys(WritePlan::steps(writeApi(), $recipes, resources: ['holidays'])))->toBe(['holidays.create', 'holidays.destroy (cleanup)'])
        ->and(stepKeys(WritePlan::steps(writeApi(), $recipes, resources: ['holidays', 'holiday_calendars'])))->toBe(['holiday_calendars.create', 'holidays.create', 'holidays.destroy (cleanup)'])
        ->and(WritePlan::steps(writeApi(), $recipes, tier: 1, resources: ['holidays']))->toBe([]);
});

it('refuses recipes it cannot or may not run', function (array $recipes, string $message) {
    expect(fn() => WritePlan::steps(writeApi(), $recipes))->toThrow(RuntimeException::class, $message);
})->with([
    'a forbidden resource' => [['invitations' => ['create' => []]], '"invitations" is never written: it leaves Productive or changes the account.'],
    'an unknown resource' => [['gadgets' => ['create' => []]], 'The recipe of "gadgets" names no resource of the API.'],
    'a missing operation' => [['tax_rates' => ['create' => [], 'update' => []]], '"tax_rates" has no "update" operation.'],
    'a sending action' => [['tax_rates' => ['create' => [], 'actions' => ['send' => null]]], 'The recipe of "tax_rates" may not run "send": it sends something out of Productive.'],
    'a sending create' => [['tax_rates#sent' => ['create_with' => 'send', 'create' => []]], 'The recipe of "tax_rates" may not run "send": it sends something out of Productive.'],
    'a forbidden resource under a label' => [['invitations#again' => ['create' => []]], '"invitations" is never written: it leaves Productive or changes the account.'],
    'an unknown operation to create with' => [['holidays#copy' => ['create_with' => 'copy', 'create' => []]], '"holidays" has no "copy" operation.'],
    'an unknown cleanup' => [['holidays' => ['create' => [], 'cleanup' => 'shred']], 'The recipe of "holidays" has cleanup "shred": use destroy, archive or none.'],
    'a cleanup that is not a string' => [['holidays' => ['create' => [], 'cleanup' => 5]], 'The recipe of "holidays" has cleanup "int": use destroy, archive or none.'],
    'an unknown step in a cleanup list' => [['holidays' => ['create' => [], 'cleanup' => ['destroy', 'shred']]], 'The recipe of "holidays" has cleanup "shred": use destroy, archive or none.'],
    'no create' => [['holidays' => []], 'A recipe\'s create, update and action attributes must be arrays.'],
    'action attributes that are not an array' => [['tax_rates' => ['create' => [], 'actions' => ['merge' => 'all']]], 'A recipe\'s create, update and action attributes must be arrays.'],
]);

it('keeps only the named attributes and ignores actions that are not a map', function () {
    $steps = WritePlan::steps(writeApi(), ['tax_rates' => ['create' => ['name' => 'SDK check', 0 => 'positional'], 'actions' => 'archive', 'cleanup' => 'none']]);

    expect($steps)->toHaveCount(1)
        ->and($steps[0]->attributes)->toBe(['name' => 'SDK check']);
});
