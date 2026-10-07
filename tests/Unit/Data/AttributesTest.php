<?php

declare(strict_types=1);

use Axyr\Productive\Data\Attributes;

enum AttributesTestPriority: int
{
    case Low = 1;
    case High = 3;
}

function attributes(mixed $value): Attributes
{
    return new Attributes(['value' => $value]);
}

it('knows which keys exist, including null values', function () {
    $attributes = new Attributes(['a' => null]);

    expect($attributes->has('a'))->toBeTrue()
        ->and($attributes->has('b'))->toBeFalse()
        ->and($attributes->mixed('a'))->toBeNull()
        ->and($attributes->mixed('b'))->toBeNull();
});

it('reads strings', function (mixed $value, ?string $expected) {
    expect(attributes($value)->string('value'))->toBe($expected);
})->with([
    ['text', 'text'],
    ['', ''],
    [42, '42'],
    [1.5, '1.5'],
    [true, null],
    [null, null],
    [['x'], null],
]);

it('reads integers losslessly', function (mixed $value, ?int $expected) {
    expect(attributes($value)->int('value'))->toBe($expected);
})->with([
    [480, 480],
    [-3, -3],
    [480.0, 480],
    [480.5, null],
    ['480', 480],
    ['-12', -12],
    ['4.5', null],
    ['12abc', null],
    ['', null],
    [true, null],
    [null, null],
]);

it('reads floats', function (mixed $value, ?float $expected) {
    expect(attributes($value)->float('value'))->toBe($expected);
})->with([
    [1.5, 1.5],
    [2, 2.0],
    ['3600.00', 3600.0],
    ['abc', null],
    [true, null],
    [null, null],
]);

it('reads booleans', function (mixed $value, ?bool $expected) {
    expect(attributes($value)->bool('value'))->toBe($expected);
})->with([
    [true, true],
    [false, false],
    [1, true],
    [0, false],
    ['1', true],
    ['0', false],
    ['true', true],
    ['false', false],
    ['yes', null],
    [2, null],
    [null, null],
]);

it('reads calendar dates at midnight', function () {
    $date = attributes('2026-03-31')->date('value');

    expect($date?->format('Y-m-d H:i:s'))->toBe('2026-03-31 00:00:00')
        ->and(attributes('2026-02-30')->date('value'))->toBeNull()
        ->and(attributes('31-03-2026')->date('value'))->toBeNull()
        ->and(attributes('2026-03-31T10:00:00Z')->date('value'))->toBeNull()
        ->and(attributes(null)->date('value'))->toBeNull();
});

it('reads date-times with their offset', function () {
    $dateTime = attributes('2026-01-15T10:00:00.000+02:00')->dateTime('value');

    expect($dateTime?->format(DATE_ATOM))->toBe('2026-01-15T10:00:00+02:00')
        ->and(attributes('not a date')->dateTime('value'))->toBeNull()
        ->and(attributes('')->dateTime('value'))->toBeNull()
        ->and(attributes(null)->dateTime('value'))->toBeNull();
});

it('reads objects and lists', function () {
    expect(attributes(['42' => 'x'])->object('value'))->toBe(['42' => 'x'])
        ->and(attributes(['a', 'b'])->object('value'))->toBe(['a', 'b'])
        ->and(attributes('x')->object('value'))->toBeNull()
        ->and(attributes(['a', 'b'])->list('value'))->toBe(['a', 'b'])
        ->and(attributes([])->list('value'))->toBe([])
        ->and(attributes(['k' => 'v'])->list('value'))->toBeNull()
        ->and(attributes(null)->list('value'))->toBeNull();
});

it('reads backed enums', function () {
    expect(attributes(3)->enum('value', AttributesTestPriority::class))->toBe(AttributesTestPriority::High)
        ->and(attributes(2)->enum('value', AttributesTestPriority::class))->toBeNull()
        ->and(attributes('1')->enum('value', AttributesTestPriority::class))->toBe(AttributesTestPriority::Low)
        ->and(attributes('abc')->enum('value', AttributesTestPriority::class))->toBeNull()
        ->and(attributes(null)->enum('value', AttributesTestPriority::class))->toBeNull()
        ->and(attributes(1.0)->enum('value', AttributesTestPriority::class))->toBeNull();
});
