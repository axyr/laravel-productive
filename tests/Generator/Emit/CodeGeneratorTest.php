<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\ClientEmitter;
use Axyr\Productive\Generator\Emit\CodeGenerator;
use Axyr\Productive\Generator\Emit\ContractTestEmitter;
use Axyr\Productive\Generator\Emit\EnumEmitter;
use Axyr\Productive\Generator\Emit\FactoryEmitter;
use Axyr\Productive\Generator\Emit\InputEmitter;
use Axyr\Productive\Generator\Emit\ModelEmitter;
use Axyr\Productive\Generator\Emit\ResourceEmitter;
use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;

/*
 * Which files CodeGenerator writes and what it passes to each emitter (paths, documentation links,
 * summaries, enum descriptions). The emitters' own output is pinned in their own tests.
 */

function generatorInput(string $class, bool $required = false): Input
{
    return new Input($class, 'task', [new Attribute('title', 'title', AttributeType::String, required: $required)]);
}

function generatorOperation(string $key, string $method, OperationKind $kind, string $http, string $path, ?Input $input = null, bool $bulk = false): Operation
{
    $parameters = str_contains($path, '{id}') ? ['id'] : [];
    $response = $kind === OperationKind::Index || $bulk ? ResponseKind::Collection : ResponseKind::Resource;

    return new Operation($key, $method, $kind, $http, $path, $parameters, $response, $bulk, true, $key, $input, body: $input === null ? BodyKind::None : BodyKind::Attributes);
}

/**
 * @return array{Api, list<Resource>}
 */
function generatorApi(): array
{
    $create = generatorInput('CreateTaskData', required: true);
    $resources = [
        new Resource('projects', 'ProjectResource', 'Axyr\\Productive\\Resources', 'projects', 'Project', [generatorOperation('projects.index', 'query', OperationKind::Index, 'GET', 'projects')]),
        new Resource('tasks', 'TaskResource', 'Axyr\\Productive\\Resources', 'tasks', 'Task', [
            generatorOperation('tasks.index', 'query', OperationKind::Index, 'GET', 'tasks'),
            generatorOperation('tasks.create', 'create', OperationKind::Create, 'POST', 'tasks', $create),
            generatorOperation('tasks.create_bulk', 'bulkCreate', OperationKind::CreateBulk, 'POST', 'tasks', $create, bulk: true),
            generatorOperation('tasks.update', 'update', OperationKind::Update, 'PATCH', 'tasks/{id}', generatorInput('UpdateTaskData')),
            generatorOperation('tasks.move_to_list', 'moveToList', OperationKind::Action, 'PATCH', 'tasks/{id}/move_to_list', generatorInput('MoveToListTaskData')),
        ], sorts: ['title', '-title']),
        new Resource('reports/time_reports', 'TimeReportResource', 'Axyr\\Productive\\Resources\\Reports', 'time_reports', 'TimeReport', [generatorOperation('reports.time_reports.index', 'query', OperationKind::Index, 'GET', 'reports/time_reports')], groups: ['person'], supportsCursor: false, reportRateLimit: true),
        new Resource('public/project_pages', 'PublicProjectPageResource', 'Axyr\\Productive\\Resources\\Public', 'projects', 'Project', [generatorOperation('public.project_pages.index', 'query', OperationKind::Index, 'GET', 'public/project_pages')]),
    ];
    $models = [
        new Model('Project', 'projects', 'A project.', [], []),
        new Model('Task', 'tasks', 'A task.', [new Attribute('title', 'title', AttributeType::String)], [new Relationship('project', false, 'projects')], ['title' => 'Plan']),
        new Model('TimeReport', 'time_reports', '', [], []),
        new Model('Unused', 'unused', '', [], []),
    ];

    return [new Api([...$resources, new Resource('unused', 'UnusedResource', 'X', 'unused', 'Unused', [])], $models), $resources];
}

it('writes every file of the enabled resources, wired with the right links and summaries', function () {
    [$api, [$projects, $tasks, $timeReports, $publicPages]] = generatorApi();
    [$project, $task, $timeReport] = $api->models;
    $classByType = ['projects' => 'Project', 'tasks' => 'Task', 'time_reports' => 'TimeReport'];
    $see = 'https://developer.productive.io/reference/resources/';

    $files = (new CodeGenerator($api, ['projects', 'tasks', 'reports/time_reports', 'public/project_pages'], ['Project' => 'project_aliases', 'Ghost' => 'ghosts']))->files();

    expect($files)->toEqual([
        // A model shared by two resources links to the first one's reference.
        'src/Data/Models/Project.php' => (new ModelEmitter($classByType))->emit($project, $see . 'projects'),
        'src/Testing/Factories/ProjectFactory.php' => FactoryEmitter::emit($project),
        'src/Data/Models/Task.php' => (new ModelEmitter($classByType))->emit($task, $see . 'tasks'),
        'src/Testing/Factories/TaskFactory.php' => FactoryEmitter::emit($task),
        'src/Data/Models/TimeReport.php' => (new ModelEmitter($classByType))->emit($timeReport, $see . 'reports'),
        'src/Testing/Factories/TimeReportFactory.php' => FactoryEmitter::emit($timeReport),
        'src/Resources/ProjectResource.php' => ResourceEmitter::emit($projects, 'Project', 'A project.', $see . 'projects'),
        'tests/Contract/Generated/ProjectResourceTest.php' => ContractTestEmitter::emit($projects, 'Project'),
        'src/Resources/TaskResource.php' => ResourceEmitter::emit($tasks, 'Task', 'A task.', $see . 'tasks'),
        'tests/Contract/Generated/TaskResourceTest.php' => ContractTestEmitter::emit($tasks, 'Task'),
        'src/Enums/TaskSort.php' => EnumEmitter::sort('TaskSort', 'Sort options for the tasks list.', ['title', '-title']),
        'src/Resources/Reports/TimeReportResource.php' => ResourceEmitter::emit($timeReports, 'TimeReport', '', $see . 'reports'),
        'tests/Contract/Generated/TimeReportResourceTest.php' => ContractTestEmitter::emit($timeReports, 'TimeReport'),
        'src/Enums/TimeReportGroup.php' => EnumEmitter::group('TimeReportGroup', 'Grouping dimensions for the time report.', ['person']),
        'src/Resources/Public/PublicProjectPageResource.php' => ResourceEmitter::emit($publicPages, 'Project', 'A project.', $see . 'project-pages'),
        'tests/Contract/Generated/PublicProjectPageResourceTest.php' => ContractTestEmitter::emit($publicPages, 'Project'),
        'src/Data/Input/CreateTaskData.php' => InputEmitter::emit($tasks->operations[1]->input, 'Attributes for creating a task.'),
        'src/Data/Input/UpdateTaskData.php' => InputEmitter::emit($tasks->operations[3]->input, 'Attributes for updating a task. Fields left out are not changed; null clears a field.'),
        'src/Data/Input/MoveToListTaskData.php' => InputEmitter::emit($tasks->operations[4]->input, 'Attributes for the "move to list" action on a task.'),
        'src/Data/ModelMap.php' => ClientEmitter::modelMap([$project, $task, $timeReport], ['Project' => 'project_aliases', 'Ghost' => 'ghosts']),
        ...ClientEmitter::emit([$projects, $tasks, $timeReports, $publicPages]),
    ])
        ->and(array_keys($files))->toBe([
            'src/Data/Models/Project.php',
            'src/Testing/Factories/ProjectFactory.php',
            'src/Data/Models/Task.php',
            'src/Testing/Factories/TaskFactory.php',
            'src/Data/Models/TimeReport.php',
            'src/Testing/Factories/TimeReportFactory.php',
            'src/Resources/ProjectResource.php',
            'tests/Contract/Generated/ProjectResourceTest.php',
            'src/Resources/TaskResource.php',
            'tests/Contract/Generated/TaskResourceTest.php',
            'src/Enums/TaskSort.php',
            'src/Resources/Reports/TimeReportResource.php',
            'tests/Contract/Generated/TimeReportResourceTest.php',
            'src/Enums/TimeReportGroup.php',
            'src/Resources/Public/PublicProjectPageResource.php',
            'tests/Contract/Generated/PublicProjectPageResourceTest.php',
            'src/Data/Input/CreateTaskData.php',
            'src/Data/Input/UpdateTaskData.php',
            'src/Data/Input/MoveToListTaskData.php',
            'src/Data/ModelMap.php',
            'src/Concerns/ProvidesResources.php',
            'src/Resources/Reports/Reports.php',
            'src/Resources/Public/PublicResources.php',
            'src/ProductiveFacade.php',
        ]);
});

it('refuses an enabled resource without a model', function () {
    $api = new Api([new Resource('tags', 'TagResource', 'X', 'tags', null, [])], []);

    expect(fn() => (new CodeGenerator($api, ['tags']))->files())->toThrow(RuntimeException::class, 'tags has no model to generate.');
});
