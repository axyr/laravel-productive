<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Model
{
    /**
     * @param  list<Attribute>  $attributes  Sorted by name.
     * @param  list<Relationship>  $relationships  Sorted by name.
     * @param  array<string, mixed>  $example  The example resource's attributes, used as factory defaults.
     */
    public function __construct(
        public string $class,
        public string $type,
        public string $description,
        public array $attributes,
        public array $relationships,
        public array $example = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'type' => $this->type,
            'description' => $this->description,
            'attributes' => array_map(fn(Attribute $attribute): array => $attribute->toArray(), $this->attributes),
            'relationships' => array_map(fn(Relationship $relationship): array => $relationship->toArray(), $this->relationships),
            'example' => $this->example,
        ];
    }
}
