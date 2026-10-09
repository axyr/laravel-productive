<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Attribute
{
    /**
     * @param  list<int|string>  $enum  Allowed values, when the spec lists them.
     * @param  AttributeType|null  $items  The item type of a list, when the spec says.
     */
    public function __construct(
        public string $name,
        public string $property,
        public AttributeType $type,
        public string $description = '',
        public bool $required = false,
        public array $enum = [],
        public ?AttributeType $items = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'property' => $this->property,
            'type' => $this->type->value,
            'description' => $this->description,
            'required' => $this->required,
            'enum' => $this->enum,
            'items' => $this->items?->value,
        ], fn(mixed $value): bool => $value !== '' && $value !== false && $value !== [] && $value !== null);
    }
}
