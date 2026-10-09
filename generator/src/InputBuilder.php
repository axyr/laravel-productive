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
     * Most bodies are JSON:API documents; a few are plain JSON objects such as {"markdown": "…"}.
     *
     * @param  array<string, mixed>  $operation
     * @param  bool  $allOptional  Updates share the create schema, whose "required" list does not apply to them.
     */
    public function build(array $operation, string $class, bool $allOptional = false): ?Input
    {
        $body = Spec::map($operation['requestBody'] ?? []);
        [$attributes, $plain] = $this->bodySchema($body);
        $properties = Spec::map($attributes['properties'] ?? []);

        if ($properties === []) {
            return null;
        }

        $required = $allOptional ? [] : Spec::strings($attributes['required'] ?? [], 'Required attributes');

        return new Input($class, self::bodyName($body), self::order($this->reader->attributes($properties, required: $required, model: false), $required), $plain);
    }

    /**
     * The schema holding the attributes, and whether it is a plain JSON object rather than JSON:API.
     *
     * @param  array<string, mixed>  $body
     * @return array{array<string, mixed>, bool}
     */
    private function bodySchema(array $body): array
    {
        $root = $this->rootSchema($body);
        $plain = $root !== [] && ! array_key_exists('data', Spec::map($root['properties'] ?? []));

        return [$plain ? $root : $this->attributesSchema($root), $plain];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function rootSchema(array $body): array
    {
        $content = Spec::map($this->spec->resolve($body)['content'] ?? []);

        return $this->spec->resolve(Spec::map(reset($content) ?: [])['schema'] ?? []);
    }

    /**
     * The attributes schema of a JSON:API request document; bulk documents carry it per item.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function attributesSchema(array $schema): array
    {
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
