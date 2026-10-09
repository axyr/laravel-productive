<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Naming;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Every generated file for the enabled resources, by path relative to the project root.
 */
final readonly class CodeGenerator
{
    /**
     * @param  list<string>  $enabled  Resource paths to generate.
     * @param  array<string, string>  $typeAliases  Extra JSON:API types by model class.
     */
    public function __construct(
        private Api $api,
        private array $enabled,
        private array $typeAliases = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public function files(): array
    {
        $resources = $this->resources();
        $models = $this->models($resources);
        $classByType = array_column(array_map(fn(array $entry): array => [$entry[0]->type, $entry[0]->class], $models), 1, 0);
        $files = [];

        foreach ($models as [$model, $path]) {
            $files['src/Data/Models/' . $model->class . '.php'] = (new ModelEmitter($classByType))->emit($model, self::see($path));
            $files['src/Testing/Factories/' . $model->class . 'Factory.php'] = FactoryEmitter::emit($model);
        }

        foreach ($resources as $resource) {
            $files = [...$files, ...$this->resourceFiles($resource)];
        }

        foreach (self::inputs($resources) as [$input, $operation, $resource]) {
            $files['src/Data/Input/' . $input->class . '.php'] = InputEmitter::emit($input, self::inputSummary($operation, $resource));
        }

        $files['src/Data/ModelMap.php'] = ClientEmitter::modelMap(array_column($models, 0), $this->typeAliases);

        return [...$files, ...ClientEmitter::emit($resources)];
    }

    /**
     * @return list<Resource>
     */
    private function resources(): array
    {
        return array_map(
            fn(string $path): Resource => $this->api->resource($path) ?? throw new RuntimeException(sprintf('generator/config/resources.php: "%s" is not a resource in the spec.', $path)),
            $this->enabled,
        );
    }

    /**
     * Each model once, with the path of the first resource that returns it (for its documentation link).
     *
     * @param  list<Resource>  $resources
     * @return array<string, array{Model, string}>
     */
    private function models(array $resources): array
    {
        $models = [];

        foreach ($resources as $resource) {
            $model = $this->modelOf($resource);
            $models[$model->class] = [$model, $resource->path];
        }

        ksort($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function resourceFiles(Resource $resource): array
    {
        $name = Naming::modelName(basename($resource->path));
        $model = $this->modelOf($resource)->class;
        $directory = 'src/' . str_replace('\\', '/', substr($resource->namespace, strlen('Axyr\\Productive\\')));
        $files = [
            $directory . '/' . $resource->class . '.php' => ResourceEmitter::emit($resource, $model, $this->modelOf($resource)->description, self::see($resource->path)),
            'tests/Contract/Generated/' . $resource->class . 'Test.php' => ContractTestEmitter::emit($resource, $model),
        ];

        if ($resource->sorts !== []) {
            $files['src/Enums/' . $name . 'Sort.php'] = EnumEmitter::sort($name . 'Sort', 'Sort options for the ' . self::human($resource->path) . ' list.', $resource->sorts);
        }

        if ($resource->groups !== []) {
            $files['src/Enums/' . $name . 'Group.php'] = EnumEmitter::group($name . 'Group', 'Grouping dimensions for the ' . Str::singular(self::human($resource->path)) . '.', $resource->groups);
        }

        return $files;
    }

    /**
     * @param  list<Resource>  $resources
     * @return array<string, array{Input, Operation, Resource}>  An operation using each input; operations sharing an input get the same summary.
     */
    private static function inputs(array $resources): array
    {
        $inputs = [];

        foreach ($resources as $resource) {
            foreach ($resource->operations as $operation) {
                if ($operation->input !== null) {
                    $inputs[$operation->input->class] = [$operation->input, $operation, $resource];
                }
            }
        }

        return $inputs;
    }

    private static function inputSummary(Operation $operation, Resource $resource): string
    {
        $subject = Str::singular(self::human($resource->path));

        return match ($operation->kind) {
            OperationKind::Create, OperationKind::CreateBulk => 'Attributes for creating a ' . $subject . '.',
            OperationKind::Update, OperationKind::UpdateBulk => 'Attributes for updating a ' . $subject . '. Fields left out are not changed; null clears a field.',
            default => 'Attributes for the "' . str_replace('_', ' ', $operation->actionName()) . '" action on a ' . $subject . '.',
        };
    }

    private function modelOf(Resource $resource): Model
    {
        return $this->api->model((string) $resource->model) ?? throw new RuntimeException(sprintf('%s has no model to generate.', $resource->path));
    }

    private static function see(string $path): string
    {
        $slug = str_starts_with($path, 'reports/') ? 'reports' : str_replace('_', '-', basename($path));

        return 'https://developer.productive.io/reference/resources/' . $slug;
    }

    private static function human(string $path): string
    {
        return str_replace('_', ' ', basename($path));
    }
}
