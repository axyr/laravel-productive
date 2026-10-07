<?php

declare(strict_types=1);

namespace Axyr\Productive\JsonApi;

/**
 * Identity map of every resource in a document (primary data and `included`), used to
 * resolve relationships. Lookups never recurse, so cyclic includes are safe.
 */
final readonly class ResourceIndex
{
    /** @var array<string, ResourceObject> */
    private array $resources;

    /**
     * @param  list<ResourceObject>  $resources
     */
    public function __construct(array $resources = [])
    {
        $indexed = [];

        foreach ($resources as $resource) {
            $indexed[$resource->identifier()->key()] ??= $resource;
        }

        $this->resources = $indexed;
    }

    public function find(ResourceIdentifier $identifier): ?ResourceObject
    {
        return $this->resources[$identifier->key()] ?? null;
    }

    public function count(): int
    {
        return count($this->resources);
    }
}
