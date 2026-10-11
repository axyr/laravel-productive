<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Relationship
{
    /**
     * @param  string|null  $targetType  JSON:API type of the related resource, null when it is polymorphic or unknown.
     * @param  bool  $undocumented  Not in the spec: only seen in recorded responses.
     */
    public function __construct(
        public string $name,
        public bool $toMany,
        public ?string $targetType,
        public bool $undocumented = false,
    ) {}

    /**
     * @return array{name: string, to_many: bool, target_type: string|null, undocumented?: true}
     */
    public function toArray(): array
    {
        $array = ['name' => $this->name, 'to_many' => $this->toMany, 'target_type' => $this->targetType];

        return $this->undocumented ? [...$array, 'undocumented' => true] : $array;
    }
}
