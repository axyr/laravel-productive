<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Recording\ValueKind;

it('names the kind of a value', function (mixed $value, ValueKind $kind) {
    expect(ValueKind::detect($value))->toBe($kind);
})->with([
    [null, ValueKind::Null],
    [false, ValueKind::Bool],
    [0, ValueKind::Int],
    [-3, ValueKind::Int],
    [1.5, ValueKind::Float],
    ['42', ValueKind::IntegerString],
    ['-42', ValueKind::IntegerString],
    ['5000.00', ValueKind::DecimalString],
    ['-0.5', ValueKind::DecimalString],
    ['2026-10-09', ValueKind::Date],
    ['2026-10-09T16:11:16.000+02:00', ValueKind::DateTime],
    ['2026-10-09 16:11', ValueKind::DateTime],
    ['', ValueKind::String],
    ['42a', ValueKind::String],
    ['a42', ValueKind::String],
    ['1.5.6', ValueKind::String],
    ['.5', ValueKind::String],
    ['5.', ValueKind::String],
    ['x2026-10-09', ValueKind::String],
    ['2026-10-09x', ValueKind::String],
    ['2026-10-9', ValueKind::String],
    ['26-10-09T10:00', ValueKind::String],
    ['2026-10-09X10:00', ValueKind::String],
    ['2026-10-09T1000', ValueKind::String],
    ['Invoice 12', ValueKind::String],
    ['[["2026-10-09", null, [8, 8], 66220]]', ValueKind::JsonText],
    ['{"a": 1}', ValueKind::JsonText],
    ['[]', ValueKind::JsonText],
    ['"quoted"', ValueKind::String],
    ['[not json', ValueKind::String],
    [[], ValueKind::Empty],
    [[1, 2], ValueKind::List],
    [['a' => 1], ValueKind::Object],
    [[1 => 'a'], ValueKind::Object],
]);

it('gives a sample that is of its own kind', function (ValueKind $kind) {
    expect(ValueKind::detect($kind->sample()))->toBe($kind);
})->with(ValueKind::cases());

it('samples each kind with a fixed value; an integer is one a boolean reader cannot read', function (ValueKind $kind, mixed $sample) {
    expect($kind->sample())->toBe($sample);
})->with([
    [ValueKind::Null, null],
    [ValueKind::Bool, true],
    [ValueKind::Int, 7],
    [ValueKind::Float, 7.5],
    [ValueKind::IntegerString, '7'],
    [ValueKind::DecimalString, '7.50'],
    [ValueKind::Date, '2026-01-02'],
    [ValueKind::DateTime, '2026-01-02T03:04:05.000+01:00'],
    [ValueKind::String, 'text'],
    [ValueKind::JsonText, '[["a"]]'],
    [ValueKind::Empty, []],
    [ValueKind::List, ['a']],
    [ValueKind::Object, ['a' => 1]],
]);

it('treats only null and empty as uninformative', function (ValueKind $kind) {
    expect($kind->isInformative())->toBe(! in_array($kind, [ValueKind::Null, ValueKind::Empty], true));
})->with(ValueKind::cases());

it('derives the type that fits every informative kind', function (array $kinds, ?AttributeType $type) {
    expect(ValueKind::typeOf($kinds))->toBe($type);
})->with([
    'nothing' => [[], null],
    'only null and empty' => [[ValueKind::Null, ValueKind::Empty], null],
    'bool' => [[ValueKind::Bool, ValueKind::Null], AttributeType::Bool],
    'int' => [[ValueKind::Int, ValueKind::Int], AttributeType::Int],
    'int and float widen' => [[ValueKind::Int, ValueKind::Float], AttributeType::Float],
    'float' => [[ValueKind::Float], AttributeType::Float],
    'date' => [[ValueKind::Date], AttributeType::Date],
    'date and date-time' => [[ValueKind::Date, ValueKind::DateTime], AttributeType::DateTime],
    'date-time' => [[ValueKind::DateTime, ValueKind::Empty], AttributeType::DateTime],
    'strings' => [[ValueKind::String, ValueKind::DecimalString], AttributeType::String],
    'numeric strings' => [[ValueKind::IntegerString], AttributeType::String],
    'decimal string' => [[ValueKind::DecimalString], AttributeType::String],
    'string and date' => [[ValueKind::String, ValueKind::Date, ValueKind::DateTime, ValueKind::IntegerString], AttributeType::String],
    'list' => [[ValueKind::List, ValueKind::Empty], AttributeType::List],
    'object' => [[ValueKind::Object], AttributeType::Object],
    'list and object' => [[ValueKind::List, ValueKind::Object], AttributeType::Mixed],
    'json text' => [[ValueKind::JsonText, ValueKind::Null], AttributeType::Json],
    'json text and text' => [[ValueKind::JsonText, ValueKind::String], AttributeType::String],
    'json text and a list' => [[ValueKind::JsonText, ValueKind::List], AttributeType::Mixed],
    'int and string' => [[ValueKind::Int, ValueKind::String], AttributeType::Mixed],
    'bool and int' => [[ValueKind::Bool, ValueKind::Int], AttributeType::Mixed],
    'float and decimal string' => [[ValueKind::Float, ValueKind::DecimalString], AttributeType::Mixed],
]);
