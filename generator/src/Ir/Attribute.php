<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Attribute
{
    /**
     * @param  list<int|string>  $enum  Allowed values, when the spec lists them.
     * @param  AttributeType|null  $items  The item type of a list, or the value type of an object map, when the spec says.
     * @param  AttributeType|null  $specType  The spec's type, when a recorded response overrode it.
     * @param  bool  $undocumented  Not in the spec: only seen in recorded responses.
     * @param  list<int|string>  $itemEnum  Allowed values of a list's items or an object's values, when the spec lists them.
     */
    public function __construct(
        public string $name,
        public string $property,
        public AttributeType $type,
        public string $description = '',
        public bool $required = false,
        public array $enum = [],
        public ?AttributeType $items = null,
        public ?AttributeType $specType = null,
        public bool $undocumented = false,
        public array $itemEnum = [],
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
            'spec_type' => $this->specType?->value,
            'undocumented' => $this->undocumented,
            'item_enum' => $this->itemEnum,
        ], fn(mixed $value): bool => $value !== '' && $value !== false && $value !== [] && $value !== null);
    }
}
