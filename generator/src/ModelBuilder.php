<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Relationship;

final readonly class ModelBuilder
{
    /**
     * @param  array<string>  $modelTypes  Types that have a model: the targets a recorded relationship may point at.
     * @param  list<string>  $removedRelationships  "type.relationship" pairs the API no longer has.
     */
    public function __construct(
        private Spec $spec,
        private SchemaReader $reader,
        private RelationshipTypes $relationshipTypes,
        private RecordedTypes $recorded = new RecordedTypes(),
        private array $modelTypes = [],
        private array $removedRelationships = [],
    ) {}

    /**
     * @param  array<string, mixed>  $responseSchema  The success response schema the model is read from.
     * @param  array<string, mixed>  $example  The example resource object of that response.
     */
    public function build(string $class, string $type, array $responseSchema, array $example): Model
    {
        $data = $this->data($responseSchema);
        $attributesSchema = $this->spec->resolve(Spec::map($data['properties'] ?? [])['attributes'] ?? []);
        $properties = Spec::map($attributesSchema['properties'] ?? []);
        $exampleAttributes = Spec::map($example['attributes'] ?? []);
        $read = $this->reader->attributes($properties, $exampleAttributes);
        // Attributes only the example has are the example's word alone: a recording that never saw them wins.
        $phantom = $this->recorded->unseen($type, array_values(array_diff(array_keys($read), array_keys($properties))));
        $attributes = $this->recorded->attributes($type, array_diff_key($read, array_flip($phantom)));
        ksort($attributes);

        return new Model(
            class: $class,
            type: $type,
            description: $this->description($attributesSchema),
            attributes: array_values($attributes),
            relationships: $this->relationships($type, $data),
            example: array_diff_key($exampleAttributes, array_flip($phantom)),
            phantom: $phantom,
        );
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    private function data(array $responseSchema): array
    {
        $data = $this->spec->resolve(Spec::map($responseSchema['properties'] ?? [])['data'] ?? []);

        return ($data['type'] ?? null) === 'array' ? $this->spec->resolve($data['items'] ?? []) : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<Relationship>
     */
    private function relationships(string $type, array $data): array
    {
        $schema = $this->spec->resolve(Spec::map($data['properties'] ?? [])['relationships'] ?? []);
        $relationships = [];

        foreach (Spec::map($schema['properties'] ?? []) as $name => $property) {
            $reference = Spec::string(Spec::map($property)['$ref'] ?? null);
            $relationships[$name] = new Relationship($name, str_ends_with($reference, '_collection_relationship'), $this->relationshipTypes->resolve($type, $name));
        }

        $relationships = $this->recorded->relationships($type, $relationships, $this->modelTypes);
        $relationships = array_filter($relationships, fn(Relationship $relationship): bool => ! in_array($type . '.' . $relationship->name, $this->removedRelationships, true));
        ksort($relationships);

        return array_values($relationships);
    }

    /**
     * The description of the `resource_*` schema the attributes point into.
     *
     * @param  array<string, mixed>  $attributesSchema
     */
    private function description(array $attributesSchema): string
    {
        foreach (Spec::map($attributesSchema['properties'] ?? []) as $property) {
            $reference = Spec::string(Spec::map($property)['$ref'] ?? null);

            if (preg_match('#^(\#/components/schemas/resource_[a-z_]+)/#', $reference, $match) === 1) {
                $description = $this->spec->pointer($match[1])['description'] ?? '';

                return is_string($description) ? trim(explode("\n", $description)[0]) : '';
            }
        }

        return '';
    }
}
