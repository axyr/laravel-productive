<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Relationship
{
    /**
     * @param  string|null  $targetType  JSON:API type of the related resource, null when it is polymorphic or unknown.
     */
    public function __construct(
        public string $name,
        public bool $toMany,
        public ?string $targetType,
    ) {}

    /**
     * @return array{name: string, to_many: bool, target_type: string|null}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'to_many' => $this->toMany, 'target_type' => $this->targetType];
    }
}
