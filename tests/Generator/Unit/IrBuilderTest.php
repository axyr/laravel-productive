<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\IrBuilder;
use Axyr\Productive\Generator\Spec;

function single(string $type, array $attributes = [], array $example = []): array
{
    return ['200' => ['content' => ['application/vnd.api+json' => ['schema' => [
        'properties' => ['data' => ['properties' => ['attributes' => ['properties' => $attributes]]]],
        'example' => ['data' => ['type' => $type, 'id' => '1', 'attributes' => $example]],
    ]]]]];
}

function smallApi(): Api
{
    $bulkBody = ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => ['time' => ['type' => 'integer']]]]]]]]]];

    return (new IrBuilder(new Spec(['paths' => [
        '/api/v2/time_entries' => [
            'post' => ['tags' => ['Time Entries - Bulk'], 'operationId' => 'time_entries-create-bulk', 'requestBody' => $bulkBody, 'responses' => single('time_entries'), 'parameters' => [['$ref' => '#/components/parameters/header_organization']]],
            'get' => ['operationId' => 'time_entries-index', 'responses' => single('time_entries')],
        ],
        '/api/v2/time_entries/{id}' => ['get' => ['operationId' => 'time_entries-show', 'responses' => single('time_entries', ['time' => ['type' => 'integer']], ['note' => 'x'])]],
        '/api/v2/time_entries/{id}/approve' => ['patch' => ['operationId' => 'time_entries-approve-approve', 'responses' => single('time_entries')]],
        '/api/v2/roles/{id}' => ['get' => ['operationId' => 'roles-show', 'responses' => single('roles')]],
        '/api/v2/agent_roles/{id}' => ['get' => ['operationId' => 'agent-roles-show', 'responses' => single('roles')]],
        '/api/v2/pages/{id}' => ['get' => ['operationId' => 'pages-show', 'responses' => single('pages')]],
        '/api/v2/public/pages/{uuid}' => ['get' => ['operationId' => 'public-pages-show', 'responses' => single('pages')]],
        '/api/v2/reports/time_reports' => ['get' => ['operationId' => 'reports-time_reports-index', 'responses' => single('new_time_reports')]],
        '/api/v2/webhooks/{id}' => ['delete' => ['operationId' => 'webhooks-destroy', 'responses' => ['204' => []]]],
    ], 'components' => ['parameters' => ['header_organization' => []]]]), methodNames: ['time_entries.approve' => 'approveEntry']))->build();
}

it('groups operations into resources sorted by path', function () {
    expect(array_map(fn($resource) => $resource->path, smallApi()->resources))
        ->toBe(['agent_roles', 'pages', 'public/pages', 'reports/time_reports', 'roles', 'time_entries', 'webhooks']);
});

it('synthesizes the single create a bulk create hides, and orders operations by kind', function () {
    $resource = smallApi()->resource('time_entries');

    expect(array_map(fn(Operation $operation): string => $operation->key, $resource->operations))
        ->toBe(['time_entries.index', 'time_entries.show', 'time_entries.create', 'time_entries.approve', 'time_entries.create_bulk'])
        ->and($resource->operation('time_entries.create')?->toArray())->toBe([
            'key' => 'time_entries.create',
            'method' => 'create',
            'kind' => 'create',
            'http' => 'POST time_entries',
            'parameters' => [],
            'response' => 'resource',
            'bulk' => false,
            'requires_organization' => true,
            'operation_id' => null,
            'input' => 'CreateTimeEntryData',
            'summary' => 'Create a single resource (synthesized: the spec only documents the bulk variant).',
        ])
        ->and($resource->operation('time_entries.approve')?->method)->toBe('approveEntry')
        ->and($resource->operation('missing'))->toBeNull();
});

it('does not trust example types that belong to another resource', function () {
    $api = smallApi();

    expect($api->resource('agent_roles')?->type)->toBe('agent_roles')
        ->and($api->resource('agent_roles')?->model)->toBe('AgentRole')
        ->and($api->resource('roles')?->type)->toBe('roles')
        ->and($api->resource('reports/time_reports')?->type)->toBe('new_time_reports')
        ->and($api->resource('public/pages')?->type)->toBe('pages')
        ->and($api->resource('public/pages')?->model)->toBe('Page');
});

it('names resource classes after their path', function () {
    $api = smallApi();

    expect($api->resource('public/pages')?->toArray()['class'])->toBe('Axyr\\Productive\\Resources\\Public\\PublicPageResource')
        ->and($api->resource('reports/time_reports')?->toArray()['class'])->toBe('Axyr\\Productive\\Resources\\Reports\\TimeReportResource')
        ->and($api->resource('reports/time_reports')?->supportsCursor)->toBeFalse()
        ->and($api->resource('reports/time_reports')?->reportRateLimit)->toBeTrue()
        ->and($api->resource('time_entries')?->toArray()['class'])->toBe('Axyr\\Productive\\Resources\\TimeEntryResource')
        ->and($api->resource('time_entries')?->supportsCursor)->toBeTrue()
        ->and($api->resource('time_entries')?->reportRateLimit)->toBeFalse();
});

it('builds one model per type from the show response', function () {
    $api = smallApi();

    expect(array_map(fn($model) => $model->class, $api->models))->toBe(['AgentRole', 'Page', 'Role', 'TimeEntry', 'TimeReport'])
        ->and(array_column($api->model('TimeEntry')?->toArray()['attributes'] ?? [], 'name'))->toBe(['note', 'time'])
        ->and($api->model('Missing'))->toBeNull()
        ->and($api->resource('webhooks')?->model)->toBeNull()
        ->and($api->resource('webhooks')?->type)->toBe('webhooks')
        ->and($api->resource('missing'))->toBeNull()
        ->and(array_keys($api->toArray()))->toBe(['resources', 'models'])
        ->and($api->operations())->toHaveCount(11);
});

it('loads the curated maps from files', function () {
    $directory = sys_get_temp_dir() . '/ir-config-' . uniqid();
    mkdir($directory);
    file_put_contents($directory . '/relationship-types.php', '<?php return ["owner" => "people", "polymorphic" => null];');
    file_put_contents($directory . '/descriptions.php', '<?php return ["a" => "A"];');
    file_put_contents($directory . '/method-names.php', '<?php return ["tasks.index" => "all"];');
    $spec = tempnam(sys_get_temp_dir(), 'spec');
    file_put_contents($spec, json_encode(['paths' => ['/api/v2/tasks' => ['get' => ['responses' => []]]]]));

    $api = IrBuilder::fromFiles($spec, $directory)->build();

    expect($api->resource('tasks')?->operation('tasks.index')?->method)->toBe('all');

    array_map(unlink(...), [...glob($directory . '/*'), $spec]);
    rmdir($directory);
});

it('stops on malformed curated maps', function (string $file, string $contents, string $message) {
    $directory = sys_get_temp_dir() . '/ir-config-' . uniqid();
    mkdir($directory);

    foreach (['relationship-types', 'descriptions', 'method-names'] as $name) {
        file_put_contents($directory . '/' . $name . '.php', $name === $file ? $contents : '<?php return [];');
    }

    try {
        expect(fn() => IrBuilder::fromFiles(Tests\Support\SpecExamples::path(), $directory))->toThrow(RuntimeException::class, $message);
    } finally {
        array_map(unlink(...), glob($directory . '/*'));
        rmdir($directory);
    }
})->with([
    ['relationship-types', '<?php return ["owner" => 5];', 'generator/config/relationship-types.php: the value for "owner" must be a string or null.'],
    ['descriptions', '<?php return ["a" => null];', 'generator/config/descriptions.php: the value for "a" must be a string.'],
    ['method-names', '<?php return ["tasks.index" => ["all"]];', 'generator/config/method-names.php: the value for "tasks.index" must be a string.'],
]);

it('does not trust an example type owned by a nested resource', function () {
    $api = (new IrBuilder(new Spec(['paths' => [
        '/api/v2/reports/time_reports' => ['get' => ['responses' => single('time_reports')]],
        '/api/v2/widgets/{id}' => ['get' => ['responses' => single('time_reports')]],
    ]])))->build();

    expect($api->resource('widgets')?->type)->toBe('widgets')
        ->and($api->resource('reports/time_reports')?->type)->toBe('time_reports');
});

it('resolves relationships to the types of the other resources by name', function () {
    $response = ['200' => ['content' => ['application/vnd.api+json' => ['schema' => [
        'properties' => ['data' => ['properties' => ['relationships' => ['properties' => ['project' => [], 'vendor' => []]]]]],
    ]]]]];
    $api = (new IrBuilder(new Spec(['paths' => [
        '/api/v2/tasks/{id}' => ['get' => ['responses' => $response]],
        '/api/v2/projects/{id}' => ['get' => ['responses' => single('projects')]],
    ]])))->build();

    expect(array_column($api->model('Task')?->toArray()['relationships'] ?? [], 'target_type', 'name'))->toBe(['project' => 'projects', 'vendor' => null]);
});
