<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

use Axyr\Productive\JsonApi\ResourceIndex;
use Axyr\Productive\JsonApi\ResourceObject;

/**
 * Maps JSON:API resource types to model classes.
 */
final readonly class ModelRegistry
{
    /** @var array<string, class-string<Model>> */
    private array $models;

    /**
     * @param  array<string, class-string<Model>>  $models  Merged over the built-in map.
     */
    public function __construct(array $models = [])
    {
        $this->models = [...ModelMap::MODELS, ...$models];
    }

    /**
     * @return class-string<Model>
     */
    public function classFor(string $type): string
    {
        return $this->models[$type] ?? GenericModel::class;
    }

    public function hydrate(ResourceObject $resource, ResourceIndex $index = new ResourceIndex()): Model
    {
        $class = $this->classFor($resource->type);

        return new $class($resource, $index, $this);
    }
}
