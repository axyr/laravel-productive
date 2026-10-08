<?php

declare(strict_types=1);

use Axyr\Productive\Data\Input\CopyTaskData;
use Axyr\Productive\Data\Input\CreateTaskData;
use Axyr\Productive\Data\Input\MoveDependentTaskData;
use Axyr\Productive\Data\Input\RepositionTaskData;
use Axyr\Productive\Data\Input\UpdateTaskData;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Enums\TaskSort;
use Axyr\Productive\Exceptions\ValidationException;
use Axyr\Productive\ProductiveFacade as Productive;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\RequestSchema;
use Tests\Support\SpecExamples;

function sentJson(HttpRequest $request): array
{
    return json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
}

it('lists tasks (tasks-index)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-index'))]);

    $tasks = Productive::tasks()->query()
        ->where('project_id', 31002)
        ->where('due_date', '>=', '2026-01-01')
        ->include('assignee', 'project')
        ->sort(TaskSort::DueDateDesc)
        ->get();

    expect($tasks)->not->toBeEmpty()
        ->and($tasks->first())->toBeInstanceOf(Task::class);
    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'GET'
        && urldecode($request->url()) === apiUrl('tasks?filter[project_id]=31002&filter[due_date][gt_eq]=2026-01-01&sort=-due_date&include=assignee,project'));
});

it('shows a task (tasks-show)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-show'))]);

    $task = Productive::tasks()->find(120501, include: ['project']);

    expect($task->id)->toBe('120501')
        ->and($task->title)->toBe('Implement user authentication');
    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'GET'
        && urldecode($request->url()) === apiUrl('tasks/120501?include=project'));
});

it('creates a task (tasks-create)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-create'), 201)]);

    $task = Productive::tasks()->create(new CreateTaskData(
        title: 'Draft Q3 launch plan',
        projectId: 31002,
        taskListId: 7781,
        assigneeId: 5634,
        dueDate: '2026-06-15',
        workflowStatusId: 101,
    ));

    expect($task)->toBeInstanceOf(Task::class);
    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValid('tasks-create', sentJson($request));

        return $request->method() === 'POST'
            && $request->url() === apiUrl('tasks')
            && $request->header('Content-Type') === ['application/vnd.api+json']
            && sentJson($request) === ['data' => ['type' => 'tasks', 'attributes' => [
                'title' => 'Draft Q3 launch plan',
                'project_id' => 31002,
                'task_list_id' => 7781,
                'assignee_id' => 5634,
                'due_date' => '2026-06-15',
                'workflow_status_id' => 101,
            ]]];
    });
});

it('accepts raw attribute arrays', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-create'), 201)]);

    Productive::tasks()->create(['title' => 'Raw', 'project_id' => 1, 'task_list_id' => 2, 'brand_new_attribute' => true]);

    Http::assertSent(fn(HttpRequest $request): bool => sentJson($request)['data']['attributes']['brand_new_attribute'] === true);
});

it('surfaces validation errors (tasks-create 422)', function () {
    fakeHttp(['*' => Http::response(['errors' => [['status' => 422, 'title' => 'Invalid Attribute', 'detail' => "can't be blank", 'source' => ['pointer' => '/data/attributes/title']]]], 422)]);

    try {
        Productive::tasks()->create(['project_id' => 1]);
    } catch (ValidationException $exception) {
        expect($exception->first('title'))->toBe("can't be blank");

        return;
    }

    $this->fail('No validation exception thrown.');
});

it('updates a task (tasks-update)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-update'))]);

    Productive::tasks()->update(120501, new UpdateTaskData(title: 'Renamed', dueDate: null));

    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValidAttributes('tasks-update', sentJson($request));

        return $request->method() === 'PATCH'
            && $request->url() === apiUrl('tasks/120501')
            && sentJson($request) === ['data' => ['type' => 'tasks', 'id' => '120501', 'attributes' => ['due_date' => null, 'title' => 'Renamed']]];
    });
});

it('deletes a task (tasks-destroy)', function () {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::tasks()->delete(120501);

    Http::assertSent(fn(HttpRequest $request): bool => $request->method() === 'DELETE'
        && $request->url() === apiUrl('tasks/120501')
        && $request->body() === '');
});

it('copies a task (tasks-copy-copy)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-copy-copy'), 201)]);

    $copy = Productive::tasks()->copy(new CopyTaskData(
        title: 'Launch plan (copy)',
        templateId: 120501,
        projectId: 31002,
        taskListId: 7781,
        workflowStatusId: 101,
        private: false,
    ));

    expect($copy)->toBeInstanceOf(Task::class);
    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValid('tasks-copy-copy', sentJson($request));

        return $request->method() === 'POST' && $request->url() === apiUrl('tasks/copy');
    });
});

it('repositions a task (tasks-reposition-reposition)', function () {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::tasks()->reposition(120501, new RepositionTaskData(moveAfterId: 555));

    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValid('tasks-reposition-reposition', sentJson($request));

        return $request->method() === 'PATCH'
            && $request->url() === apiUrl('tasks/120501/reposition')
            && sentJson($request) === ['data' => ['type' => 'tasks', 'id' => '120501', 'attributes' => ['move_after_id' => 555]]];
    });
});

it('moves dependent tasks (tasks-move-dependent-move-dependent)', function () {
    fakeHttp(['*' => Http::response(SpecExamples::response('tasks-move-dependent-move-dependent'))]);

    $task = Productive::tasks()->moveDependent(120501, new MoveDependentTaskData(daysCount: 3, skipRootTask: true));

    expect($task)->toBeInstanceOf(Task::class);
    Http::assertSent(function (HttpRequest $request): bool {
        RequestSchema::assertValid('tasks-move-dependent-move-dependent', sentJson($request));

        return $request->method() === 'PATCH' && $request->url() === apiUrl('tasks/120501/move_dependent');
    });
});

it('encodes ids in paths', function () {
    fakeHttp(['*' => Http::response(null, 204)]);

    Productive::tasks()->delete('a/b');

    Http::assertSent(fn(HttpRequest $request): bool => $request->url() === apiUrl('tasks/a%2Fb'));
});
