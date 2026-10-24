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
        $attributes = $this->recorded->attributes($type, $read);
        ksort($attributes);

        return new Model(
            class: $class,
            type: $type,
            description: $this->description($attributesSchema),
            attributes: array_values($attributes),
            relationships: $this->relationships($type, $data),
            example: $exampleAttributes,
            // Attributes only the example has, which no recording saw: kept, and listed as doubtful.
            unseen: $this->recorded->unseen($type, array_diff(array_keys($read), array_keys($properties))),
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
     * A recorded relationship seen without data has no target yet: it gets one by the same rules as
     * a documented relationship, from the curated map or its name ("child_pages" → pages).
     */
    private function typed(string $type, Relationship $relationship): Relationship
    {
        if (! $relationship->undocumented || $relationship->targetType !== null) {
            return $relationship;
        }

        return new Relationship($relationship->name, $relationship->toMany, $this->relationshipTypes->resolve($type, $relationship->name), undocumented: true);
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

        $relationships = array_map(fn(Relationship $relationship): Relationship => $this->typed($type, $relationship), $this->recorded->relationships($type, $relationships, $this->modelTypes));
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
