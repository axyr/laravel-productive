<?php

declare(strict_types=1);

use Axyr\Productive\Exceptions\InvalidQueryException;
use Axyr\Productive\Query\Condition;
use Axyr\Productive\Query\Operator;

enum ConditionTestStatus: int
{
    case Open = 1;
}

enum ConditionTestName: string
{
    case Active = 'active';
}

it('normalizes values to their wire format', function (mixed $value, string $expected) {
    expect((new Condition('field', Operator::Eq, $value))->value)->toBe($expected);
})->with([
    'string' => ['abc', 'abc'],
    'int' => [42, '42'],
    'float' => [1.5, '1.5'],
    'true' => [true, 'true'],
    'false' => [false, 'false'],
    'int enum' => [ConditionTestStatus::Open, '1'],
    'string enum' => [ConditionTestName::Active, 'active'],
    'date' => [new DateTimeImmutable('2026-01-02T03:04:05+02:00'), '2026-01-02T03:04:05+02:00'],
    'list' => [[1, '2', ConditionTestStatus::Open], '1,2,1'],
]);

it('remembers whether the operator was explicit', function () {
    expect((new Condition('a', Operator::Eq, 1))->explicitOperator)->toBeTrue()
        ->and((new Condition('a', Operator::Eq, 1, explicitOperator: false))->explicitOperator)->toBeFalse();
});

it('turns dotted fields into nested segments', function () {
    expect((new Condition('custom_fields.42', Operator::Eq, 'x'))->path())->toBe('[custom_fields][42]')
        ->and((new Condition('project_id', Operator::Eq, 1))->path())->toBe('[project_id]');
});

it('rejects invalid field names', function (string $field) {
    new Condition($field, Operator::Eq, 1);
})->with(['', 'project id', 'a[b]', 'a.', '.a', 'a..b', '$op'])->throws(InvalidQueryException::class, 'Invalid filter field');

it('rejects empty lists', function () {
    new Condition('ids', Operator::Eq, []);
})->throws(InvalidQueryException::class, 'The filter on "ids" was given an empty list.');

it('rejects values it cannot serialize', function (mixed $value, string $type) {
    expect(fn() => new Condition('field', Operator::Eq, $value))
        ->toThrow(InvalidQueryException::class, sprintf('The filter on "field" was given a %s;', $type));
})->with([
    [null, 'null'],
    [new stdClass(), 'stdClass'],
    [[[1]], 'array'],
]);
