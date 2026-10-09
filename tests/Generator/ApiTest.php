<?php

declare(strict_types=1);

use Axyr\Productive\Data\Model as BaseModel;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\Enums\TaskSort;
use Axyr\Productive\Enums\TimeEntrySort;
use Axyr\Productive\Enums\TimeReportGroup;
use Axyr\Productive\Enums\TimeReportSort;
use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\IrBuilder;
use Axyr\Productive\Generator\Naming;
use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\Resources\Reports\TimeReportResource;
use Axyr\Productive\Resources\Resource as BaseResource;
use Axyr\Productive\Resources\TaskResource;
use Axyr\Productive\Resources\TimeEntryResource;
use Illuminate\Support\Str;
use Tests\Support\SpecExamples;

function api(): Api
{
    static $api = null;

    return $api ??= IrBuilder::fromFiles(SpecExamples::path(), dirname(__DIR__, 2) . '/generator/config')->build();
}

/**
 * @return list<string>
 */
function declaredMethods(string $class, int $filter = ReflectionMethod::IS_PUBLIC): array
{
    $methods = array_filter((new ReflectionClass($class))->getMethods($filter), fn(ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && ! $method->isStatic());

    return array_values(array_map(fn(ReflectionMethod $method): string => $method->getName(), $methods));
}

/**
 * @return list<string>
 */
function sorted(array $values): array
{
    sort($values);

    return array_values($values);
}

it('covers every operation in the spec exactly once', function () {
    $specIds = [];

    foreach (SpecExamples::spec()['paths'] as $operations) {
        foreach ($operations as $operation) {
            if (is_array($operation) && isset($operation['operationId'])) {
                $specIds[] = $operation['operationId'];
            }
        }
    }

    $irIds = array_values(array_filter(array_map(fn(Operation $operation): ?string => $operation->operationId, api()->operations())));

    expect(count($specIds))->toBe(668)
        ->and(sorted($irIds))->toBe(sorted($specIds));
});

it('synthesizes only the single creates that bulk operations hide', function () {
    $synthesized = array_values(array_filter(api()->operations(), fn(Operation $operation): bool => $operation->isSynthesized()));

    expect(sorted(array_map(fn(Operation $operation): string => $operation->key, $synthesized)))
        ->toBe(['expense_line_items.create', 'line_items.create', 'time_entries.create']);
});

it('gives every operation a unique key and every method a unique name per resource', function () {
    $keys = array_map(fn(Operation $operation): string => $operation->key, api()->operations());

    expect(array_unique($keys))->toHaveCount(count($keys));

    foreach (api()->resources as $resource) {
        $methods = array_map(fn(Operation $operation): string => $operation->method, $resource->operations);

        expect(array_unique($methods))->toHaveCount(count($methods), $resource->path);
    }
});

it('gives every resource and model a unique class name', function () {
    $resources = array_map(fn(Resource $resource): string => $resource->namespace . '\\' . $resource->class, api()->resources);
    $models = array_map(fn(Model $model): string => $model->class, api()->models);

    expect(array_unique($resources))->toHaveCount(count($resources))
        ->and(array_unique($models))->toHaveCount(count($models))
        ->and(count($resources))->toBe(140)
        ->and(count($models))->toBe(137);
});

it('never generates a name that collides with the base classes', function () {
    $resourceMethods = array_map(fn(ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(BaseResource::class))->getMethods());
    $modelMethods = array_map(fn(ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(BaseModel::class))->getMethods());

    foreach (api()->operations() as $operation) {
        expect($resourceMethods)->not->toContain($operation->method);
    }

    foreach (api()->models as $model) {
        $properties = array_map(fn(Attribute $attribute): string => $attribute->property, $model->attributes);

        expect($properties)->not->toContain('id')
            ->and($properties)->not->toContain('type')
            ->and(array_unique($properties))->toHaveCount(count($properties), $model->class);

        foreach ($model->relationships as $relationship) {
            expect($modelMethods)->not->toContain(Naming::camel($relationship->name));
        }
    }
});

it('only points relationships at types that have a model', function () {
    $types = array_map(fn(Model $model): string => $model->type, api()->models);

    foreach (api()->models as $model) {
        foreach ($model->relationships as $relationship) {
            expect($relationship->targetType === null || in_array($relationship->targetType, $types, true))->toBeTrue($model->class . '.' . $relationship->name);
        }
    }
});

it('uses one set of attributes per input class', function () {
    $definitions = [];

    foreach (api()->operations() as $operation) {
        if ($operation->input !== null) {
            $definitions[$operation->input->class][] = json_encode($operation->input->toArray()['attributes']);
        }
    }

    foreach ($definitions as $class => $variants) {
        expect(array_unique($variants))->toHaveCount(1, $class);
    }
});

it('describes the golden resources exactly as they were written', function (string $path, string $resourceClass, string $modelClass) {
    $resource = api()->resource($path);
    $source = (string) file_get_contents((new ReflectionClass($resourceClass))->getFileName());
    preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)+)'/", $source, $keys);

    expect($resource)->not->toBeNull()
        ->and(sorted(declaredMethods($resourceClass)))->toBe(sorted(array_map(fn(Operation $operation): string => $operation->method, $resource->operations)))
        ->and(sorted(array_unique($keys[1])))->toBe(sorted(array_map(fn(Operation $operation): string => $operation->key, $resource->operations)))
        ->and($resource->model)->toBe((new ReflectionClass($modelClass))->getShortName());

    foreach ($resource->operations as $operation) {
        $returnType = (string) (new ReflectionMethod($resourceClass, $operation->method))->getReturnType();

        expect($returnType)->toBe(match ($operation->response) {
            ResponseKind::NoContent => 'void',
            ResponseKind::Collection => $operation->method === 'query' ? Axyr\Productive\Resources\PendingQuery::class : ModelCollection::class,
            default => $modelClass,
        }, $operation->key);
    }
})->with([
    ['tasks', TaskResource::class, Task::class],
    ['time_entries', TimeEntryResource::class, TimeEntry::class],
    ['reports/time_reports', TimeReportResource::class, TimeReport::class],
]);

it('describes the golden models exactly as they were written', function (string $class) {
    $model = api()->model((new ReflectionClass($class))->getShortName());
    $properties = array_filter((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC), fn(ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class);

    expect($model)->not->toBeNull()
        ->and($model->type)->toBe($class::TYPE)
        ->and(sorted(array_map(fn(ReflectionProperty $property): string => $property->getName(), $properties)))->toBe(sorted(array_map(fn(Attribute $attribute): string => $attribute->property, $model->attributes)))
        ->and(sorted(declaredMethods($class)))->toBe(sorted(array_map(fn(Relationship $relationship): string => Naming::camel($relationship->name), $model->relationships)));
})->with([Task::class, TimeEntry::class]);

it('lists the time report relationships the spec documents', function () {
    $relationships = array_map(fn(Relationship $relationship): string => Naming::camel($relationship->name), api()->model('TimeReport')->relationships);

    expect($relationships)->toContain(...declaredMethods(TimeReport::class));
});

it('describes the golden inputs exactly as they were written', function () {
    foreach (api()->resources as $resource) {
        foreach ($resource->operations as $operation) {
            $class = 'Axyr\\Productive\\Data\\Input\\' . $operation->input?->class;

            if ($operation->input === null || ! class_exists($class)) {
                continue;
            }

            $parameters = (new ReflectionClass($class))->getConstructor()->getParameters();

            expect(array_map(fn(ReflectionParameter $parameter): string => Str::snake($parameter->getName()), $parameters))
                ->toBe(array_map(fn(Attribute $attribute): string => $attribute->name, $operation->input->attributes))
                ->and(array_map(fn(ReflectionParameter $parameter): bool => ! $parameter->isOptional(), $parameters))
                ->toBe(array_map(fn(Attribute $attribute): bool => $attribute->required, $operation->input->attributes));
        }
    }
});

it('lists the sort and group values of the golden enums', function () {
    expect(api()->resource('tasks')->sorts)->toBe(array_map(fn(TaskSort $case): string => $case->value, TaskSort::cases()))
        ->and(api()->resource('time_entries')->sorts)->toBe(array_map(fn(TimeEntrySort $case): string => $case->value, TimeEntrySort::cases()))
        ->and(api()->resource('reports/time_reports')->sorts)->toBe(array_map(fn(TimeReportSort $case): string => $case->value, TimeReportSort::cases()))
        ->and(api()->resource('reports/time_reports')->groups)->toBe(array_map(fn(TimeReportGroup $case): string => $case->value, TimeReportGroup::cases()))
        ->and(api()->resource('reports/time_reports')->supportsCursor)->toBeFalse()
        ->and(api()->resource('reports/time_reports')->reportRateLimit)->toBeTrue()
        ->and(api()->resource('tasks')->groups)->toBe([]);
});

it('matches the committed API description', function () {
    $current = json_encode(api()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $committed = (string) file_get_contents(dirname(__DIR__, 2) . '/generator/api.json');

    expect(sha1($current))->toBe(sha1($committed), 'generator/api.json is out of date: run `composer generate:ir` and review the diff.');
})->group('drift');

it('describes the operations that are not plain JSON:API', function () {
    $operations = [];

    foreach (api()->operations() as $operation) {
        if ($operation->response === ResponseKind::Raw || $operation->body !== Axyr\Productive\Generator\Ir\BodyKind::None && $operation->body !== Axyr\Productive\Generator\Ir\BodyKind::Attributes) {
            $operations[$operation->key] = $operation->response->value . ' / ' . $operation->body->value;
        }
    }

    ksort($operations);

    expect($operations)->toBe([
        'boards.copy' => 'optional_resource / optional_data',
        'contracts.generate' => 'resource / optional_data',
        'dashboards.copy' => 'optional_resource / optional_data',
        'deal_statuses.merge' => 'resource / optional_data',
        'deals.copy' => 'optional_resource / bulk_item',
        'deals.create_from_origin' => 'optional_resource / optional_data',
        'document_styles.copy' => 'optional_resource / optional_data',
        'document_types.copy' => 'optional_resource / optional_data',
        'integrations.create' => 'resource / data',
        'line_items.generate' => 'resource / optional_data',
        'pages.append_html' => 'resource / plain',
        'pages.append_markdown' => 'resource / plain',
        'pages.replace_body_with_html' => 'resource / plain',
        'pages.replace_body_with_markdown' => 'resource / plain',
        'people.merge' => 'resource / optional_data',
        'proposals.create' => 'resource / data',
        'proposals.signed_pdf' => 'raw / none',
        'proposals.update' => 'resource / data',
        'public.artifacts.attachments_auth' => 'raw / none',
        'rate_cards.copy' => 'optional_resource / optional_data',
        'resource_requests.create' => 'resource / data',
        'resource_requests.resolve' => 'resource / optional_data',
        'resource_requests.update' => 'resource / data',
        'revenue_distributions.create' => 'resource / data',
        'revenue_distributions.update' => 'resource / data',
        'service_types.merge' => 'resource / optional_data',
        'sessions.machine' => 'raw / optional_data',
        'surveys.copy' => 'optional_resource / optional_data',
    ]);
});

it('exports every input with its attributes', function () {
    $exported = api()->toArray()['inputs'];

    expect($exported)->toHaveCount(216)
        ->and(array_column($exported, 'class'))->toContain('CreateTaskData', 'AppendMarkdownPageData')
        ->and($exported[array_search('CreateTaskData', array_column($exported, 'class'), true)]['attributes'][0]['name'])->toBe('title');
});

it('types currency codes and leaves the deal-or-budget report polymorphic', function () {
    foreach (api()->models as $model) {
        foreach ($model->attributes as $attribute) {
            if (str_starts_with($attribute->name, 'currency')) {
                expect($attribute->type)->not->toBe(Axyr\Productive\Generator\Ir\AttributeType::Mixed, $model->class . '.' . $attribute->name);
            }
        }

        foreach ($model->relationships as $relationship) {
            if ($relationship->name === 'deal_or_budget_report') {
                expect($relationship->targetType)->toBeNull();
            }

            if ($relationship->name === 'project_manager') {
                expect($relationship->targetType)->toBe('people');
            }
        }
    }
});
