<?php

declare(strict_types=1);

use Axyr\Productive\Data\InputData;
use Axyr\Productive\Generator\Emit\Samples;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Tests\Support\RequestSchema;

/*
 * Every generated input class, built with a sample value for every attribute, must produce a
 * body the spec accepts. The contract tests only send the required attributes; this checks the
 * rest: names, types, enums and date formats.
 */

/**
 * @return array<string, array{class-string<InputData>, string, string, string, list<array<string, mixed>>}>
 */
function inputCases(): array
{
    $api = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/generator/api.json'), true, flags: JSON_THROW_ON_ERROR);
    $inputs = array_column($api['inputs'], null, 'class');
    $enabled = require dirname(__DIR__, 2) . '/generator/config/resources.php';
    $cases = [];

    foreach (array_filter($api['resources'], fn(array $resource): bool => in_array($resource['path'], $enabled, true)) as $resource) {
        foreach ($resource['operations'] as $operation) {
            $class = $operation['input'] ?? null;

            if ($class === null || ($operation['operation_id'] ?? null) === null || $operation['bulk'] || isset($cases[$class])) {
                continue;
            }

            $cases[$class] = ['Axyr\\Productive\\Data\\Input\\' . $class, $operation['operation_id'], $operation['kind'], $operation['body'], $resource['type'], $inputs[$class]['attributes']];
        }
    }

    ksort($cases);

    return $cases;
}

it('builds bodies the spec accepts from every attribute', function (string $class, string $operationId, string $kind, string $body, string $type, array $attributes) {
    $arguments = [];

    foreach ($attributes as $attribute) {
        $ir = new Attribute($attribute['name'], $attribute['property'], AttributeType::from($attribute['type']), enum: $attribute['enum'] ?? [], items: isset($attribute['items']) ? AttributeType::from($attribute['items']) : null, itemEnum: $attribute['item_enum'] ?? []);
        $arguments[$attribute['property']] = Samples::value($ir);
    }

    $values = (new $class(...$arguments))->toAttributes();

    // The spec marks time attributes (a task's due_time) as `format: time`, an RFC 3339 time with
    // seconds and offset, but describes them as "HH:MM", which is what the SDK sends.
    foreach (array_filter($attributes, fn(array $attribute): bool => $attribute['type'] === 'time') as $attribute) {
        expect($values[$attribute['name']])->toMatch('/^\d{2}:\d{2}$/');
    }

    $checked = array_diff_key($values, array_flip(array_column(array_filter($attributes, fn(array $attribute): bool => $attribute['type'] === 'time'), 'name')));

    match (true) {
        $body === 'plain' => RequestSchema::assertValid($operationId, $checked),
        $kind === 'update' => RequestSchema::assertValidAttributes($operationId, ['data' => ['type' => $type, 'id' => '1', 'attributes' => $checked]], $type),
        $body === 'bulk_item' => RequestSchema::assertValid($operationId, ['data' => [['type' => $type, 'attributes' => $checked]]], $type),
        default => RequestSchema::assertValid($operationId, ['data' => ['type' => $type, 'attributes' => $checked]], $type),
    };

    expect(array_keys($values))->toBe(array_column($attributes, 'name'));
})->with(inputCases());
