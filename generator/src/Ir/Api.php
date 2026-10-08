<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

/**
 * The intermediate representation the emitters work from: every resource with its operations,
 * every model and every input, already named and typed.
 */
final readonly class Api
{
    /**
     * @param  list<Resource>  $resources  Sorted by path.
     * @param  list<Model>  $models  Sorted by class.
     */
    public function __construct(
        public array $resources,
        public array $models,
    ) {}

    public function resource(string $path): ?Resource
    {
        foreach ($this->resources as $resource) {
            if ($resource->path === $path) {
                return $resource;
            }
        }

        return null;
    }

    public function model(string $class): ?Model
    {
        foreach ($this->models as $model) {
            if ($model->class === $class) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return list<Operation>
     */
    public function operations(): array
    {
        return array_merge(...array_map(fn(Resource $resource): array => $resource->operations, $this->resources));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resources' => array_map(fn(Resource $resource): array => $resource->toArray(), $this->resources),
            'models' => array_map(fn(Model $model): array => $model->toArray(), $this->models),
        ];
    }
}
