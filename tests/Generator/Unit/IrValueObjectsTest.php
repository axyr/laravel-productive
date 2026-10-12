<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;

it('knows which operation kinds are bulk', function (OperationKind $kind, bool $bulk) {
    expect($kind->isBulk())->toBe($bulk);
})->with([
    [OperationKind::Index, false],
    [OperationKind::Show, false],
    [OperationKind::Create, false],
    [OperationKind::Update, false],
    [OperationKind::Destroy, false],
    [OperationKind::Action, false],
    [OperationKind::CreateBulk, true],
    [OperationKind::UpdateBulk, true],
    [OperationKind::DestroyBulk, true],
    [OperationKind::ActionBulk, true],
]);

it('exports attributes without empty members', function () {
    expect((new Attribute('title', 'title', AttributeType::String, 'Name.', true, ['a']))->toArray())
        ->toBe(['name' => 'title', 'property' => 'title', 'type' => 'string', 'description' => 'Name.', 'required' => true, 'enum' => ['a']])
        ->and((new Attribute('a', 'a', AttributeType::Mixed))->toArray())->toBe(['name' => 'a', 'property' => 'a', 'type' => 'mixed'])
        ->and((new Attribute('t', 't', AttributeType::List, items: AttributeType::String, specType: AttributeType::String, undocumented: true, itemEnum: ['note', 'task']))->toArray())
        ->toBe(['name' => 't', 'property' => 't', 'type' => 'list', 'items' => 'string', 'spec_type' => 'string', 'undocumented' => true, 'item_enum' => ['note', 'task']]);
});

it('exports a resource with its operations', function () {
    $operation = new Operation('tasks.index', 'query', OperationKind::Index, 'GET', 'tasks', [], ResponseKind::Collection, false, true, 'tasks-index');
    $resource = new Resource('tasks', 'TaskResource', 'Axyr\\Productive\\Resources', 'tasks', 'Task', [$operation], ['title'], ['person'], supportsCursor: false, reportRateLimit: true);

    expect($resource->toArray())->toBe([
        'path' => 'tasks',
        'class' => 'Axyr\\Productive\\Resources\\TaskResource',
        'type' => 'tasks',
        'model' => 'Task',
        'supports_cursor' => false,
        'report_rate_limit' => true,
        'sorts' => ['title'],
        'groups' => ['person'],
        'operations' => [$operation->toArray()],
    ]);
});

it('exports the API with each input once, sorted by class', function () {
    $create = new Input('CreateTaskData', 'task', []);
    $update = new Input('AssignTaskData', 'task', []);
    $operation = fn(string $key, ?Input $input): Operation => new Operation($key, 'x', OperationKind::Action, 'POST', 'tasks', [], ResponseKind::Resource, false, true, $key, $input);
    $resource = new Resource('tasks', 'TaskResource', 'X', 'tasks', 'Task', [$operation('a', $create), $operation('b', null), $operation('c', $update), $operation('d', $create)]);
    $model = new Model('Task', 'tasks', '', [], []);
    $api = new Api([$resource], [$model]);

    expect($api->inputs())->toBe([$update, $create])
        ->and($api->toArray())->toBe([
            'resources' => [$resource->toArray()],
            'models' => [$model->toArray()],
            'inputs' => [$update->toArray(), $create->toArray()],
        ]);
});
