<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use RuntimeException;

/**
 * Builds the intermediate representation of the whole API from the spec and the curated maps.
 */
final readonly class IrBuilder
{
    private const KIND_ORDER = ['index', 'show', 'create', 'update', 'destroy', 'action', 'create_bulk', 'update_bulk', 'destroy_bulk', 'action_bulk'];

    /**
     * @param  array<string, string|null>  $relationshipTypes
     * @param  array<string, string>  $descriptions
     * @param  array<string, string>  $methodNames
     */
    public function __construct(
        private Spec $spec,
        private array $relationshipTypes = [],
        private array $descriptions = [],
        private array $methodNames = [],
    ) {}

    public static function fromFiles(string $specPath, string $configDirectory): self
    {
        return new self(
            Spec::fromFile($specPath),
            self::stringMap(self::config($configDirectory, 'relationship-types'), 'relationship-types', nullable: true),
            self::stringMap(self::config($configDirectory, 'descriptions'), 'descriptions'),
            self::stringMap(self::config($configDirectory, 'method-names'), 'method-names'),
        );
    }

    public function build(): Api
    {
        $grouped = $this->groupByResource();
        $sources = self::withTrustedTypes(array_map(fn(array $entries): ModelSource => ModelSource::pick($this->spec, $entries), $grouped));
        $models = $this->models($sources);
        $classByType = array_column(array_map(fn(Model $model): array => [$model->type, $model->class], $models), 1, 0);
        $resources = [];

        foreach ($grouped as $path => $entries) {
            $resources[] = $this->resource($path, $entries, $sources[$path], $classByType);
        }

        usort($models, fn(Model $a, Model $b): int => strcmp($a->class, $b->class));

        return new Api($resources, $models);
    }

    /**
     * @return array<string, list<array{ClassifiedPath, string, array<string, mixed>}>>
     */
    private function groupByResource(): array
    {
        $operations = $this->spec->operations();
        $classifier = new PathClassifier(array_column($operations, 0));
        $grouped = [];

        foreach ($operations as [$path, $method, $operation]) {
            $classified = $classifier->classify($path);
            $grouped[$classified->resource][] = [$classified, $method, $operation];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * One model per JSON:API type, named after the first resource (by path) that returns it.
     *
     * @param  array<string, ModelSource>  $sources
     * @return array<string, Model>
     */
    private function models(array $sources): array
    {
        $types = array_map(fn(ModelSource $source): string => $source->type, $sources);
        $reader = new SchemaReader($this->spec, $this->descriptions);
        $builder = new ModelBuilder($this->spec, $reader, RelationshipTypes::fromExamples($this->spec, $types, $this->relationshipTypes));
        $models = [];

        foreach ($sources as $path => $source) {
            if ($source->schema !== null && ! isset($models[$source->type])) {
                $models[$source->type] = $builder->build(Naming::modelName(basename($path)), $source->type, $source->schema, $source->example);
            }
        }

        return $models;
    }

    /**
     * @param  list<array{ClassifiedPath, string, array<string, mixed>}>  $entries
     * @param  array<string, string>  $classByType
     */
    private function resource(string $path, array $entries, ModelSource $source, array $classByType): Resource
    {
        $name = Naming::modelName(basename($path));
        $builder = new OperationBuilder($this->spec, new InputBuilder($this->spec, new SchemaReader($this->spec, $this->descriptions)), $this->methodNames);
        $operations = array_map(fn(array $entry): Operation => $builder->build($entry[0], $entry[1], $entry[2], $name), $entries);
        [$namespace, $class] = self::className($path, $name);

        return new Resource(
            path: $path,
            class: $class,
            namespace: $namespace,
            type: $source->type,
            model: $source->schema === null ? null : $classByType[$source->type],
            operations: self::sortOperations(self::withSynthesizedCreate($operations)),
            sorts: ParameterValues::forIndex($this->spec, $entries, 'sort'),
            groups: ParameterValues::forIndex($this->spec, $entries, 'group'),
            supportsCursor: ! str_starts_with($path, 'reports/'),
            reportRateLimit: str_starts_with($path, 'reports/'),
        );
    }

    /**
     * Reports live in Resources\\Reports; public endpoints in Resources\\Public with a "Public" prefix.
     *
     * @return array{string, string}
     */
    private static function className(string $path, string $name): array
    {
        return match (true) {
            str_starts_with($path, 'reports/') => ['Axyr\\Productive\\Resources\\Reports', $name . 'Resource'],
            str_starts_with($path, 'public/') => ['Axyr\\Productive\\Resources\\Public', 'Public' . $name . 'Resource'],
            default => ['Axyr\\Productive\\Resources', $name . 'Resource'],
        };
    }

    /**
     * Some spec examples carry the type of another resource ("agent_roles" claims "roles").
     * An example type is only trusted when no other resource path owns it; resources that end
     * in the same segment ("public/pages" and "pages") genuinely share their type.
     *
     * @param  array<string, ModelSource>  $sources
     * @return array<string, ModelSource>
     */
    private static function withTrustedTypes(array $sources): array
    {
        $owned = array_map(basename(...), array_keys($sources));

        foreach ($sources as $path => $source) {
            $own = basename($path);

            if ($source->type !== $own && in_array($source->type, $owned, true)) {
                $sources[$path] = new ModelSource($own, $source->schema, $source->example);
            }
        }

        return $sources;
    }

    /**
     * Bulk and single create share a path and method, which OpenAPI cannot express, so the spec
     * only documents the bulk one. The single create is added back with the same input. (A
     * collection POST is either one or the other, so a documented single create never collides.)
     *
     * @param  list<Operation>  $operations
     * @return list<Operation>
     */
    private static function withSynthesizedCreate(array $operations): array
    {
        foreach ($operations as $operation) {
            if ($operation->kind === OperationKind::CreateBulk) {
                $operations[] = new Operation(
                    key: substr($operation->key, 0, -strlen('_bulk')),
                    method: 'create',
                    kind: OperationKind::Create,
                    httpMethod: 'POST',
                    path: $operation->path,
                    parameters: [],
                    response: ResponseKind::Resource,
                    bulk: false,
                    requiresOrganization: $operation->requiresOrganization,
                    operationId: null,
                    input: $operation->input,
                    summary: 'Create a single resource (synthesized: the spec only documents the bulk variant).',
                );
            }
        }

        return $operations;
    }

    /**
     * @param  list<Operation>  $operations
     * @return list<Operation>
     */
    private static function sortOperations(array $operations): array
    {
        usort($operations, fn(Operation $a, Operation $b): int => [array_search($a->kind->value, self::KIND_ORDER, true), $a->key] <=> [array_search($b->kind->value, self::KIND_ORDER, true), $b->key]);

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return ($nullable is true ? array<string, string|null> : array<string, string>)
     */
    private static function stringMap(array $config, string $name, bool $nullable = false): array
    {
        foreach ($config as $key => $value) {
            if (! self::isAllowed($value, $nullable)) {
                throw new RuntimeException(sprintf('generator/config/%s.php: the value for "%s" must be a string%s.', $name, $key, $nullable ? ' or null' : ''));
            }
        }

        /** @var array<string, string|null> $config */
        return $config;
    }

    private static function isAllowed(mixed $value, bool $nullable): bool
    {
        return is_string($value) || ($nullable && $value === null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(string $directory, string $name): array
    {
        /** @var array<string, mixed> $config */
        $config = require $directory . '/' . $name . '.php';

        return $config;
    }
}
