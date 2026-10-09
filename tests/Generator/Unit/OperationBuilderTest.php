<?php

declare(strict_types=1);

use Axyr\Productive\Generator\ClassifiedPath;
use Axyr\Productive\Generator\InputBuilder;
use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\OperationBuilder;
use Axyr\Productive\Generator\ResponseShape;
use Axyr\Productive\Generator\SchemaReader;
use Axyr\Productive\Generator\Spec;

function path(string $resource, bool $member = false, array $actions = [], array $parameters = []): ClassifiedPath
{
    $parameters = $member && $parameters === [] ? ['id'] : $parameters;

    return new ClassifiedPath($resource . ($member ? '/{id}' : '') . ($actions === [] ? '' : '/' . implode('/', $actions)), $resource, $member, $parameters, $actions);
}

it('derives the operation kind from path and method', function (ClassifiedPath $path, string $method, bool $bulk, OperationKind $kind) {
    expect(OperationBuilder::kind($path, $method, $bulk))->toBe($kind);
})->with([
    [path('tasks'), 'GET', false, OperationKind::Index],
    [path('tasks'), 'POST', false, OperationKind::Create],
    [path('tasks'), 'POST', true, OperationKind::CreateBulk],
    [path('tasks'), 'PATCH', true, OperationKind::UpdateBulk],
    [path('tasks'), 'PUT', true, OperationKind::UpdateBulk],
    [path('tasks'), 'DELETE', true, OperationKind::DestroyBulk],
    [path('tasks', true), 'GET', false, OperationKind::Show],
    [path('tasks', true), 'PATCH', false, OperationKind::Update],
    [path('sessions', true, ['validate_otp']), 'PUT', false, OperationKind::Action],
    [path('tasks', true), 'DELETE', false, OperationKind::Destroy],
    [path('tasks', false, ['copy']), 'POST', false, OperationKind::Action],
    [path('time_entries', false, ['approve']), 'PATCH', true, OperationKind::ActionBulk],
    [path('time_entries', true, ['approve']), 'PATCH', true, OperationKind::Action],
]);

it('derives keys and method names', function (ClassifiedPath $path, OperationKind $kind, string $key, string $method) {
    expect(OperationBuilder::key($path, $kind))->toBe($key)
        ->and(OperationBuilder::method($kind, (string) $path->action()))->toBe($method);
})->with([
    [path('tasks'), OperationKind::Index, 'tasks.index', 'query'],
    [path('tasks', true), OperationKind::Show, 'tasks.show', 'find'],
    [path('tasks'), OperationKind::Create, 'tasks.create', 'create'],
    [path('tasks', true), OperationKind::Update, 'tasks.update', 'update'],
    [path('tasks', true), OperationKind::Destroy, 'tasks.destroy', 'delete'],
    [path('tasks'), OperationKind::CreateBulk, 'tasks.create_bulk', 'bulkCreate'],
    [path('tasks'), OperationKind::UpdateBulk, 'tasks.update_bulk', 'bulkUpdate'],
    [path('tasks'), OperationKind::DestroyBulk, 'tasks.destroy_bulk', 'bulkDelete'],
    [path('tasks', true, ['move_dependent']), OperationKind::Action, 'tasks.move_dependent', 'moveDependent'],
    [path('time_entries', false, ['approve']), OperationKind::ActionBulk, 'time_entries.approve_bulk', 'bulkApprove'],
    [path('expenses', false, ['bulk_approve']), OperationKind::ActionBulk, 'expenses.bulk_approve', 'bulkApprove'],
    [path('reports/time_reports'), OperationKind::Index, 'reports.time_reports.index', 'query'],
]);

it('derives the response kind', function (OperationKind $kind, ResponseShape $shape, ResponseKind $response) {
    expect(OperationBuilder::response($kind, $shape))->toBe($response);
})->with([
    [OperationKind::Index, new ResponseShape(), ResponseKind::Collection],
    [OperationKind::CreateBulk, new ResponseShape(resource: true), ResponseKind::Collection],
    [OperationKind::UpdateBulk, new ResponseShape(), ResponseKind::Collection],
    [OperationKind::DestroyBulk, new ResponseShape(resource: true), ResponseKind::NoContent],
    [OperationKind::ActionBulk, new ResponseShape(collection: true), ResponseKind::NoContent],
    [OperationKind::Action, new ResponseShape(collection: true), ResponseKind::Collection],
    [OperationKind::Show, new ResponseShape(resource: true), ResponseKind::Resource],
    [OperationKind::Action, new ResponseShape(resource: true, noContent: true), ResponseKind::OptionalResource],
    [OperationKind::Update, new ResponseShape(resource: true, emptyOk: true), ResponseKind::OptionalResource],
    [OperationKind::Action, new ResponseShape(emptyOk: true), ResponseKind::Raw],
    [OperationKind::Destroy, new ResponseShape(noContent: true), ResponseKind::NoContent],
    [OperationKind::Destroy, new ResponseShape(), ResponseKind::NoContent],
    [OperationKind::Show, new ResponseShape(resource: true, plainJson: true), ResponseKind::Raw],
    [OperationKind::Action, new ResponseShape(resource: true, noContent: true, plainJson: true), ResponseKind::Raw],
]);

it('derives the request body kind', function (OperationKind $kind, ?Input $input, string $method, BodyKind $body, bool $member = true) {
    expect(OperationBuilder::body($kind, $input, $method, $member))->toBe($body);
})->with([
    'collection merge' => [OperationKind::Action, null, 'PATCH', BodyKind::OptionalData, false],
    'collection get' => [OperationKind::Action, null, 'GET', BodyKind::None, false],
    'collection post' => [OperationKind::Action, null, 'POST', BodyKind::OptionalData, false],
    [OperationKind::Create, new Input('CreateTaskData', 'task', []), 'POST', BodyKind::Attributes],
    [OperationKind::Action, new Input('AppendMarkdownPageData', 'page', [], plain: true), 'PATCH', BodyKind::Plain],
    [OperationKind::Action, new Input('CopyTaskData', 'task_copy', []), 'POST', BodyKind::Attributes],
    [OperationKind::Action, new Input('CopyDealData', 'deal_bulk_copy', [], bulk: true), 'POST', BodyKind::BulkItem],
    [OperationKind::CreateBulk, new Input('CreateLineItemData', 'line_item_bulk', [], bulk: true), 'POST', BodyKind::Attributes],
    [OperationKind::Action, new Input('PlainData', 'plain', [], plain: true, bulk: true), 'PATCH', BodyKind::Plain],
    [OperationKind::Create, null, 'POST', BodyKind::Data],
    [OperationKind::Update, null, 'PATCH', BodyKind::Data],
    [OperationKind::CreateBulk, null, 'POST', BodyKind::Data],
    [OperationKind::UpdateBulk, null, 'PATCH', BodyKind::Data],
    [OperationKind::Action, null, 'POST', BodyKind::OptionalData],
    [OperationKind::Action, null, 'PATCH', BodyKind::None],
    [OperationKind::Action, null, 'GET', BodyKind::None],
    [OperationKind::Show, null, 'GET', BodyKind::None],
    [OperationKind::Destroy, null, 'DELETE', BodyKind::None],
    [OperationKind::DestroyBulk, null, 'DELETE', BodyKind::None],
    [OperationKind::ActionBulk, null, 'PATCH', BodyKind::None],
]);

it('recognises bulk operations by tag or operation id', function (array $operation, bool $bulk) {
    expect(OperationBuilder::isBulk($operation))->toBe($bulk);
})->with([
    [['tags' => ['Time Entries - Bulk']], true],
    [['operationId' => 'time_entries-create-bulk'], true],
    [['tags' => ['Time Entries'], 'operationId' => 'time_entries-create'], false],
    [['tags' => 'Bulk', 'operationId' => 5], false],
    [[], false],
]);

it('stops on malformed tags', function () {
    OperationBuilder::isBulk(['tags' => ['Bulk', 5]]);
})->throws(RuntimeException::class, 'Operation tags must only contain strings, int found.');

it('builds a complete operation', function () {
    $spec = new Spec(['components' => [
        'requestBodies' => ['task_copy' => ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => ['title' => ['type' => 'string']], 'required' => ['title']]]]]]]]]],
        'parameters' => ['header_organization' => ['in' => 'header']],
    ]]);
    $builder = new OperationBuilder($spec, new InputBuilder($spec, new SchemaReader($spec)), ['tasks.reposition' => 'moveWithin']);

    $copy = $builder->build(path('tasks', false, ['copy']), 'POST', [
        'operationId' => 'tasks-copy-copy',
        'summary' => 'Copy a task',
        'parameters' => [['$ref' => '#/components/parameters/header_organization'], 'odd'],
        'requestBody' => ['$ref' => '#/components/requestBodies/task_copy'],
        'responses' => ['201' => []],
    ], 'Task');
    $reposition = $builder->build(path('tasks', true, ['reposition']), 'PATCH', ['responses' => ['204' => []], 'summary' => 5], 'Task');

    expect($copy->toArray())->toBe([
        'key' => 'tasks.copy',
        'method' => 'copy',
        'kind' => 'action',
        'http' => 'POST tasks/copy',
        'parameters' => [],
        'response' => 'no_content',
        'bulk' => false,
        'requires_organization' => true,
        'operation_id' => 'tasks-copy-copy',
        'body' => 'attributes',
        'input' => 'CopyTaskData',
        'summary' => 'Copy a task',
    ])->and($copy->input?->requestBody)->toBe('task_copy')
        ->and($reposition->method)->toBe('moveWithin')
        ->and($reposition->requiresOrganization)->toBeFalse()
        ->and($reposition->operationId)->toBeNull()
        ->and($reposition->summary)->toBe('')
        ->and($reposition->input)->toBeNull();
});

it('names inputs after the kind of operation', function (ClassifiedPath $path, string $method, bool $bulk, ?string $input, bool $required) {
    $spec = new Spec([]);
    $body = ['content' => ['application/vnd.api+json' => ['schema' => ['properties' => ['data' => ['properties' => ['attributes' => ['properties' => ['a' => ['type' => 'string']], 'required' => ['a']]]]]]]]];
    $operation = (new OperationBuilder($spec, new InputBuilder($spec, new SchemaReader($spec))))
        ->build($path, $method, ['requestBody' => $body, 'tags' => $bulk ? ['Bulk'] : []], 'Task');

    expect($operation->input?->class)->toBe($input)
        ->and($operation->input?->attributes[0]->required ?? false)->toBe($required);
})->with([
    [path('tasks'), 'POST', false, 'CreateTaskData', true],
    [path('tasks'), 'POST', true, 'CreateTaskData', true],
    [path('tasks', true), 'PATCH', false, 'UpdateTaskData', false],
    [path('tasks'), 'PATCH', true, 'UpdateTaskData', false],
    [path('tasks', true, ['move_dependent']), 'PATCH', false, 'MoveDependentTaskData', true],
    [path('tasks', true), 'GET', false, null, false],
    [path('tasks'), 'DELETE', true, null, false],
    [path('tasks', false, ['approve']), 'PATCH', true, null, false],
]);
