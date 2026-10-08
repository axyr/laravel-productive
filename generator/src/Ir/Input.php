<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Input
{
    /**
     * @param  list<Attribute>  $attributes  Required attributes first, in spec order, then optional ones by name.
     */
    public function __construct(
        public string $class,
        public string $requestBody,
        public array $attributes,
        public bool $plain = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'request_body' => $this->requestBody,
            'plain' => $this->plain,
            'attributes' => array_map(fn(Attribute $attribute): array => $attribute->toArray(), $this->attributes),
        ];
    }
}
