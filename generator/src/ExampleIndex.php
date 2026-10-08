<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

/**
 * Every resource object that appears in the spec's response examples.
 */
final class ExampleIndex
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function resourceObjects(Spec $spec): array
    {
        $objects = [];

        foreach ($spec->operations() as [, , $operation]) {
            foreach (Spec::map($operation['responses'] ?? []) as $response) {
                foreach (self::examples($spec, $spec->resolve($response)) as $example) {
                    array_push($objects, ...self::objectsIn($example));
                }
            }
        }

        return $objects;
    }

    /**
     * The resource object of the first example document in a response.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function firstResource(Spec $spec, array $response): array
    {
        $objects = array_filter(array_map(fn(array $example): array => self::firstObject($example['data'] ?? null), self::examples($spec, $response)));

        return reset($objects) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstObject(mixed $data): array
    {
        return Spec::map(is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private static function examples(Spec $spec, array $response): array
    {
        $examples = [];

        foreach (Spec::map($response['content'] ?? []) as $content) {
            $example = $spec->resolve(Spec::map($content)['schema'] ?? [])['example'] ?? null;

            if (is_array($example)) {
                $examples[] = Spec::map($example);
            }
        }

        return $examples;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<array<string, mixed>>
     */
    private static function objectsIn(array $document): array
    {
        $data = $document['data'] ?? [];
        $primary = is_array($data) && array_is_list($data) ? $data : [$data];
        $included = is_array($document['included'] ?? null) ? $document['included'] : [];

        return array_filter(
            array_map(Spec::map(...), [...$primary, ...$included]),
            fn(array $object): bool => is_string($object['type'] ?? null),
        );
    }
}
