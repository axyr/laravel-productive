<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

/**
 * Builds JSON:API request documents.
 *
 * Productive mostly accepts foreign keys as attributes (`project_id`), but relationship
 * objects are supported for endpoints that need them.
 */
final readonly class DocumentBuilder
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, ResourceIdentifier|list<ResourceIdentifier>|null>  $relationships
     * @return array{data: array<string, mixed>}
     */
    public static function resource(string $type, array $attributes = [], ?string $id = null, array $relationships = []): array
    {
        return ['data' => self::resourceObject($type, $attributes, $id, $relationships)];
    }

    /**
     * A bulk document (`ext=bulk`): one resource object per item.
     *
     * @param  list<array{id?: string, attributes?: array<string, mixed>}>  $items
     * @return array{data: list<array<string, mixed>>}
     */
    public static function bulk(string $type, array $items): array
    {
        return ['data' => array_map(
            fn(array $item): array => self::resourceObject($type, $item['attributes'] ?? [], $item['id'] ?? null),
            $items,
        )];
    }

    /**
     * A list of resource identifiers, as used by bulk deletes.
     *
     * @param  list<int|string>  $ids
     * @return array{data: list<array{type: string, id: string}>}
     */
    public static function identifiers(string $type, array $ids): array
    {
        return ['data' => array_map(
            fn(int|string $id): array => (new ResourceIdentifier($type, (string) $id))->toArray(),
            $ids,
        )];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, ResourceIdentifier|list<ResourceIdentifier>|null>  $relationships
     * @return array<string, mixed>
     */
    private static function resourceObject(string $type, array $attributes, ?string $id, array $relationships = []): array
    {
        $object = ['type' => $type];

        if ($id !== null) {
            $object['id'] = $id;
        }

        if ($attributes !== []) {
            $object['attributes'] = $attributes;
        }

        if ($relationships !== []) {
            $object['relationships'] = array_map(
                fn(ResourceIdentifier|array|null $data): array => ['data' => match (true) {
                    $data instanceof ResourceIdentifier => $data->toArray(),
                    is_array($data) => array_map(fn(ResourceIdentifier $identifier): array => $identifier->toArray(), $data),
                    default => null,
                }],
                $relationships,
            );
        }

        return $object;
    }
}
