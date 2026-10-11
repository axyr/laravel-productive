<?php

declare(strict_types=1);

use Axyr\Productive\Data\GenericModel;
use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Generator\SchemaReader;
use Axyr\Productive\JsonApi\Document;
use Illuminate\Support\Str;

/*
 * Real API responses (scrubbed, see `composer record:fixtures`) must hydrate without losing a
 * value: every non-empty attribute lands in a typed property with the same value, and every
 * relationship that came with data has an accessor.
 */

/**
 * @return array<string, array{string}>
 */
function recordedFixtures(): array
{
    $files = glob(dirname(__DIR__, 3) . '/Fixtures/Recorded/*.json') ?: [];

    return array_combine(array_map(fn(string $file): string => basename($file, '.json'), $files), array_map(fn(string $file): array => [$file], $files));
}

/**
 * Whether the typed property holds the value a lenient reader must produce from the raw one.
 */
function typedValueMatches(ReflectionProperty $property, mixed $raw, mixed $typed): bool
{
    return match ((string) $property->getType()) {
        '?int' => $typed === (int) $raw && (string) $typed === (string) $raw,
        '?float' => $typed === (float) $raw,
        '?bool' => $typed === in_array($raw, [true, 1, '1', 'true'], true),
        '?string' => $typed === (string) $raw,
        '?DateTimeImmutable' => $typed instanceof DateTimeImmutable && is_string($raw) && (strlen($raw) === 10
            ? $typed->format('Y-m-d') === $raw
            : $typed->getTimestamp() === (new DateTimeImmutable($raw))->getTimestamp()),
        default => $typed === $raw,
    };
}

it('hydrates every recorded value into its typed property', function (string $file) {
    $document = Document::fromArray(json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));
    $registry = new ModelRegistry();
    $problems = [];

    foreach ($document->resources() as $resource) {
        $model = $registry->hydrate($resource, $document->index());

        if ($model instanceof GenericModel) {
            continue;
        }

        $reflection = new ReflectionObject($model);

        foreach ($resource->attributes as $name => $raw) {
            $property = SchemaReader::property((string) $name);

            if (! $reflection->hasProperty($property) || $reflection->getProperty($property)->getDeclaringClass()->getName() === Model::class) {
                $problems[] = sprintf('%s.%s has no property', $resource->type, $name);

                continue;
            }

            // Null and empty carry no value to lose; the spec decides their type.
            if ($raw !== null && $raw !== [] && ! typedValueMatches($reflection->getProperty($property), $raw, $reflection->getProperty($property)->getValue($model))) {
                $problems[] = sprintf('%s.%s (%s) lost a %s', $resource->type, $name, $reflection->getProperty($property)->getType(), get_debug_type($raw));
            }
        }

        foreach ($resource->relationships as $name => $relationship) {
            if ($relationship->hasData && $relationship->data !== null && ! method_exists($model, Str::camel($name))) {
                $problems[] = sprintf('%s.%s has no accessor', $resource->type, $name);
            }
        }
    }

    expect(array_values(array_unique($problems)))->toBe([]);
})->with(recordedFixtures());

it('has recorded fixtures to check', function () {
    expect(count(recordedFixtures()))->toBeGreaterThan(50);
});
