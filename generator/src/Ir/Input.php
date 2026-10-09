<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Input
{
    /**
     * @param  list<Attribute>  $attributes  Required attributes first, in spec order, then optional ones by name.
     * @param  bool  $plain  The body is a plain JSON object, not a JSON:API document.
     * @param  bool  $bulk  The body is a bulk document: `data` is a list of resource objects.
     */
    public function __construct(
        public string $class,
        public string $requestBody,
        public array $attributes,
        public bool $plain = false,
        public bool $bulk = false,
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
            'bulk' => $this->bulk,
            'attributes' => array_map(fn(Attribute $attribute): array => $attribute->toArray(), $this->attributes),
        ];
    }
}
