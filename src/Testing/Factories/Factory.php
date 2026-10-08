<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing\Factories;

use Axyr\Productive\Data\Model;
use Axyr\Productive\JsonApi\ResourceObject;

/**
 * Builds models and JSON:API resource objects for tests, with realistic defaults taken
 * from the examples in Productive's API reference.
 *
 *     TaskFactory::new()->make(['title' => 'Write docs']);
 *     FakeResponse::collection(TaskFactory::new()->count(3)->resources());
 *
 * @template TModel of Model
 */
abstract class Factory
{
    private static int $sequence = 0;

    private int $count = 1;

    /** @var array<string, mixed> */
    private array $state = [];

    /** @var array<string, array{type: string, id: string}|list<array{type: string, id: string}>|null> */
    private array $relationships = [];

    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function definition(): array;

    public function count(int $count): static
    {
        $clone = clone $this;
        $clone->count = $count;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function state(array $attributes): static
    {
        $clone = clone $this;
        $clone->state = [...$this->state, ...$attributes];

        return $clone;
    }

    /**
     * Link a related resource: relatedTo('assignee', 'people', '12'), or a list of IDs for a to-many.
     *
     * @param  string|list<string>|null  $id
     */
    public function relatedTo(string $relationship, string $type, string|array|null $id): static
    {
        $clone = clone $this;
        $clone->relationships[$relationship] = match (true) {
            $id === null => null,
            is_array($id) => array_map(fn(string $item): array => ['type' => $type, 'id' => $item], $id),
            default => ['type' => $type, 'id' => $id],
        };

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $attributes  Use the "id" key to set the ID.
     * @return TModel
     */
    public function make(array $attributes = []): Model
    {
        return $this->hydrate($this->resource($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<TModel>
     */
    public function makeMany(array $attributes = []): array
    {
        return array_map(fn(array $resource): Model => $this->hydrate($resource), $this->resources($attributes));
    }

    /**
     * A JSON:API resource object, ready for FakeResponse::resource().
     *
     * @param  array<string, mixed>  $attributes
     * @return array{type: string, id: string, attributes: array<string, mixed>, relationships?: array<string, array{data: mixed}>}
     */
    public function resource(array $attributes = []): array
    {
        $attributes = [...$this->definition(), ...$this->state, ...$attributes];
        $id = $attributes['id'] ?? ++self::$sequence;
        unset($attributes['id']);

        $resource = ['type' => $this->model()::TYPE, 'id' => is_scalar($id) ? (string) $id : '', 'attributes' => $attributes];

        if ($this->relationships !== []) {
            $resource['relationships'] = array_map(fn(?array $data): array => ['data' => $data], $this->relationships);
        }

        return $resource;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{type: string, id: string, attributes: array<string, mixed>, relationships?: array<string, array{data: mixed}>}>
     */
    public function resources(array $attributes = []): array
    {
        return array_map(fn(): array => $this->resource($attributes), range(1, max(1, $this->count)));
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return TModel
     */
    private function hydrate(array $resource): Model
    {
        $class = $this->model();

        return new $class(ResourceObject::fromArray($resource));
    }
}
