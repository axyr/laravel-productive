<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

use Axyr\Productive\Exceptions\RelationshipNotIncludedException;
use Axyr\Productive\JsonApi\Relationship;
use Axyr\Productive\JsonApi\ResourceIdentifier;
use Axyr\Productive\JsonApi\ResourceIndex;
use Axyr\Productive\JsonApi\ResourceObject;
use JsonSerializable;

/**
 * A hydrated Productive resource.
 *
 * Subclasses expose typed, nullable properties for the documented attributes and typed
 * accessors for relationships. Everything Productive sends is also kept raw, so attributes
 * this SDK does not know about yet are never lost.
 */
abstract readonly class Model implements JsonSerializable
{
    /** The JSON:API resource type, e.g. "tasks". */
    public const string TYPE = '';

    public string $id;

    public string $type;

    final public function __construct(
        private ResourceObject $resource,
        private ResourceIndex $index = new ResourceIndex(),
        private ModelRegistry $registry = new ModelRegistry(),
    ) {
        $this->id = $resource->id;
        $this->type = $resource->type;
        $this->hydrate(new Attributes($resource->attributes));
    }

    abstract protected function hydrate(Attributes $attributes): void;

    /**
     * The raw attribute value exactly as Productive sent it.
     */
    public function attribute(string $name): mixed
    {
        return $this->resource->attribute($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->resource->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->resource->meta;
    }

    public function resource(): ResourceObject
    {
        return $this->resource;
    }

    /**
     * The related resource ID from the relationship's linkage data. It does not need the related
     * resource to be included, but Productive must have sent the linkage: when it sent only
     * `"meta": {"included": false}`, this throws instead of guessing, because null would wrongly
     * mean "no related resource".
     *
     * @throws RelationshipNotIncludedException
     */
    public function relationshipId(string $name): ?string
    {
        return $this->relationshipIds($name)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function relationshipIds(string $name): array
    {
        $relationship = $this->resource->relationship($name);

        if ($relationship === null || ! $relationship->hasData) {
            throw RelationshipNotIncludedException::for($this->type, $name);
        }

        return array_map(fn(ResourceIdentifier $identifier): string => $identifier->id, $relationship->identifiers());
    }

    /**
     * Any included relationship, hydrated into the model class registered for its type.
     *
     * @return Model|list<Model>|null
     */
    public function related(string $name): Model|array|null
    {
        $relationship = $this->loadedRelationship($name);
        $models = $this->hydrateRelated($relationship, $name);

        return $relationship->isToMany() ? $models : ($models[0] ?? null);
    }

    /**
     * @return array{id: string, type: string, attributes: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'attributes' => $this->resource->attributes];
    }

    /**
     * @return array{id: string, type: string, attributes: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel|null
     */
    protected function belongsTo(string $name, string $class): ?Model
    {
        $related = $this->related($name);

        return $related instanceof $class ? $related : null;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return list<TModel>
     */
    protected function hasMany(string $name, string $class): array
    {
        $relationship = $this->loadedRelationship($name);

        return array_values(array_filter(
            $this->hydrateRelated($relationship, $name),
            fn(Model $model): bool => $model instanceof $class,
        ));
    }

    private function loadedRelationship(string $name): Relationship
    {
        $relationship = $this->resource->relationship($name);

        if ($relationship === null || ! $relationship->hasData) {
            throw RelationshipNotIncludedException::for($this->type, $name);
        }

        return $relationship;
    }

    /**
     * @return list<Model>
     */
    private function hydrateRelated(Relationship $relationship, string $name): array
    {
        $models = [];

        foreach ($relationship->identifiers() as $identifier) {
            $resource = $this->index->find($identifier) ?? throw RelationshipNotIncludedException::for($this->type, $name);
            $models[] = $this->registry->hydrate($resource, $this->index);
        }

        return $models;
    }
}
