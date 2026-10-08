<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\Input;

final readonly class InputBuilder
{
    public function __construct(
        private Spec $spec,
        private SchemaReader $reader,
    ) {}

    /**
     * The input object for an operation's request body, or null when it sends no attributes.
     *
     * @param  array<string, mixed>  $operation
     * @param  bool  $allOptional  Updates share the create schema, whose "required" list does not apply to them.
     */
    public function build(array $operation, string $class, bool $allOptional = false): ?Input
    {
        $body = Spec::map($operation['requestBody'] ?? []);
        $attributes = $this->attributesSchema($body);
        $properties = Spec::map($attributes['properties'] ?? []);

        if ($properties === []) {
            return null;
        }

        $required = $allOptional ? [] : Spec::strings($attributes['required'] ?? [], 'Required attributes');

        return new Input($class, self::bodyName($body), self::order($this->reader->attributes($properties, required: $required), $required));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function attributesSchema(array $body): array
    {
        $content = Spec::map($this->spec->resolve($body)['content'] ?? []);
        $schema = $this->spec->resolve(Spec::map(reset($content) ?: [])['schema'] ?? []);
        $data = $this->spec->resolve(Spec::map($schema['properties'] ?? [])['data'] ?? []);

        if (($data['type'] ?? null) === 'array') {
            $data = $this->spec->resolve($data['items'] ?? []);
        }

        return $this->spec->resolve(Spec::map($data['properties'] ?? [])['attributes'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function bodyName(array $body): string
    {
        return basename(Spec::string($body['$ref'] ?? null));
    }

    /**
     * Required attributes first, in the order the spec lists them, then the rest by name.
     *
     * @param  array<string, Attribute>  $attributes
     * @param  list<string>  $required
     * @return list<Attribute>
     */
    private static function order(array $attributes, array $required): array
    {
        $first = array_filter(array_map(fn(string $name): ?Attribute => $attributes[$name] ?? null, $required));
        $rest = array_diff_key($attributes, array_flip($required));
        ksort($rest);

        return [...$first, ...array_values($rest)];
    }
}
