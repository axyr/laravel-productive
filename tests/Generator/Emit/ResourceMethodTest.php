<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\ResourceMethod;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;

/*
 * The exact resource method the emitter writes per kind of operation, body and response, and the
 * classes it imports. Each expectation was checked by hand against the Resource helpers it calls.
 */

function methodInput(): Input
{
    return new Input('CreateTaskData', 'task', [new Attribute('title', 'title', AttributeType::String, required: true)]);
}

/**
 * @param  list<string>  $parameters
 */
function methodOperation(OperationKind $kind, string $http, string $path, array $parameters, ResponseKind $response, bool $bulk = false, ?Input $input = null, BodyKind $body = BodyKind::None, string $summary = '', string $method = 'go'): Operation
{
    return new Operation('tasks.go', $method, $kind, $http, $path, $parameters, $response, $bulk, true, 'x', $input, $summary, $body);
}

function resourceMethod(Operation $operation): ResourceMethod
{
    return new ResourceMethod(new Resource('tasks', 'TaskResource', 'X', 'tasks', 'Task', [$operation]), $operation, 'Task');
}

it('writes the method and its imports for each kind of operation', function (Closure $operation, string $lines, array $imports) {
    $method = resourceMethod($operation());

    expect(implode("\n", $method->lines()))->toBe($lines)
        ->and($method->imports())->toBe($imports);
})->with([
    'index' => [fn(): Operation => methodOperation(OperationKind::Index, 'GET', 'tasks', [], ResponseKind::Collection, summary: 'List tasks.', method: 'query'), <<<'PHP'
            /**
             * List tasks.
             *
             * GET /tasks
             *
             * @return PendingQuery<Task>
             */
            public function query(): PendingQuery
            {
                return $this->newQuery(Task::class, 'tasks.go');
            }
        PHP, ['Axyr\\Productive\\Resources\\PendingQuery']],
    'show' => [fn(): Operation => methodOperation(OperationKind::Show, 'GET', 'tasks/{id}', ['id'], ResponseKind::Resource, summary: 'Get a task', method: 'find'), <<<'PHP'
            /**
             * Get a task.
             *
             * GET /tasks/{id}
             *
             * @param  list<string>  $include
             */
            public function find(int|string $id, array $include = []): Task
            {
                return $this->fetchOne(Task::class, $this->path($id), 'tasks.go', (new Query())->include(...$include));
            }
        PHP, ['Axyr\\Productive\\Query\\Query']],
    'create' => [fn(): Operation => methodOperation(OperationKind::Create, 'POST', 'tasks', [], ResponseKind::Resource, input: methodInput(), body: BodyKind::Attributes, method: 'create'), <<<'PHP'
            /**
             * POST /tasks
             *
             * @param  CreateTaskData|array<string, mixed>  $data
             */
            public function create(CreateTaskData|array $data): Task
            {
                return $this->write(Task::class, Method::Post, $this->path(), 'tasks.go', $data);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Http\\Method']],
    'update' => [fn(): Operation => methodOperation(OperationKind::Update, 'PATCH', 'tasks/{id}', ['id'], ResponseKind::Resource, input: methodInput(), body: BodyKind::Attributes, method: 'update'), <<<'PHP'
            /**
             * PATCH /tasks/{id}
             *
             * @param  CreateTaskData|array<string, mixed>  $data
             */
            public function update(int|string $id, CreateTaskData|array $data): Task
            {
                return $this->write(Task::class, Method::Patch, $this->path($id), 'tasks.go', $data, (string) $id);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Http\\Method']],
    'create bulk' => [fn(): Operation => methodOperation(OperationKind::CreateBulk, 'POST', 'tasks', [], ResponseKind::Collection, bulk: true, input: methodInput(), body: BodyKind::Attributes, method: 'bulkCreate'), <<<'PHP'
            /**
             * POST /tasks (bulk)
             *
             * @param  list<CreateTaskData|array<string, mixed>>  $entries
             * @return ModelCollection<Task>
             */
            public function bulkCreate(array $entries): ModelCollection
            {
                $items = array_map(fn(CreateTaskData|array $entry): array => ['attributes' => self::attributes($entry)], $entries);

                /** @var ModelCollection<Task> */
                return $this->bulkWrite(Method::Post, 'tasks.go', $items);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Pagination\\ModelCollection', 'Axyr\\Productive\\Http\\Method']],
    'create bulk without input' => [fn(): Operation => methodOperation(OperationKind::CreateBulk, 'POST', 'tasks', [], ResponseKind::Collection, bulk: true, body: BodyKind::Data, method: 'bulkCreate'), <<<'PHP'
            /**
             * POST /tasks (bulk)
             *
             * @param  list<array<string, mixed>>  $entries
             * @return ModelCollection<Task>
             */
            public function bulkCreate(array $entries): ModelCollection
            {
                $items = array_map(fn(array $entry): array => ['attributes' => self::attributes($entry)], $entries);

                /** @var ModelCollection<Task> */
                return $this->bulkWrite(Method::Post, 'tasks.go', $items);
            }
        PHP, ['Axyr\\Productive\\Pagination\\ModelCollection', 'Axyr\\Productive\\Http\\Method']],
    'update bulk' => [fn(): Operation => methodOperation(OperationKind::UpdateBulk, 'PATCH', 'tasks', [], ResponseKind::Collection, bulk: true, input: methodInput(), body: BodyKind::Attributes, method: 'bulkUpdate'), <<<'PHP'
            /**
             * PATCH /tasks (bulk)
             *
             * @param  array<int|string, CreateTaskData|array<string, mixed>>  $updates  Keyed by ID.
             * @return ModelCollection<Task>
             */
            public function bulkUpdate(array $updates): ModelCollection
            {
                $items = [];

                foreach ($updates as $id => $update) {
                    $items[] = ['id' => (string) $id, 'attributes' => self::attributes($update)];
                }

                /** @var ModelCollection<Task> */
                return $this->bulkWrite(Method::Patch, 'tasks.go', $items);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Pagination\\ModelCollection', 'Axyr\\Productive\\Http\\Method']],
    'destroy bulk' => [fn(): Operation => methodOperation(OperationKind::DestroyBulk, 'DELETE', 'tasks', [], ResponseKind::NoContent, bulk: true, method: 'bulkDelete'), <<<'PHP'
            /**
             * DELETE /tasks (bulk)
             *
             * @param  list<int|string>  $ids
             */
            public function bulkDelete(array $ids): void
            {
                $this->bulkWithoutResponse(Method::Delete, $this->path(), 'tasks.go', $ids);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'action bulk' => [fn(): Operation => methodOperation(OperationKind::ActionBulk, 'PATCH', 'tasks/approve', [], ResponseKind::NoContent, bulk: true, method: 'bulkApprove'), <<<'PHP'
            /**
             * PATCH /tasks/approve (bulk)
             *
             * @param  list<int|string>  $ids
             */
            public function bulkApprove(array $ids): void
            {
                $this->bulkWithoutResponse(Method::Patch, $this->path('approve'), 'tasks.go', $ids);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'write without a body returning the resource' => [fn(): Operation => methodOperation(OperationKind::Action, 'PATCH', 'tasks/{id}/close', ['id'], ResponseKind::Resource, method: 'close'), <<<'PHP'
            /**
             * PATCH /tasks/{id}/close
             */
            public function close(int|string $id): Task
            {
                return $this->write(Task::class, Method::Patch, $this->path($id, 'close'), 'tasks.go', null);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'plain action' => [fn(): Operation => methodOperation(OperationKind::Action, 'PATCH', 'tasks/{id}/append_markdown', ['id'], ResponseKind::Resource, input: methodInput(), body: BodyKind::Plain, method: 'appendMarkdown'), <<<'PHP'
            /**
             * PATCH /tasks/{id}/append_markdown
             *
             * @param  CreateTaskData|array<string, mixed>  $data
             */
            public function appendMarkdown(int|string $id, CreateTaskData|array $data): Task
            {
                return $this->writePlain(Task::class, Method::Patch, $this->path($id, 'append_markdown'), 'tasks.go', $data);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Http\\Method']],
    'optional body on a member' => [fn(): Operation => methodOperation(OperationKind::Action, 'PATCH', 'tasks/{id}/restore', ['id'], ResponseKind::Resource, body: BodyKind::OptionalData, method: 'restore'), <<<'PHP'
            /**
             * PATCH /tasks/{id}/restore
             *
             * @param  array<string, mixed>  $data  Attributes, if the endpoint needs any: the spec documents none. Nothing is sent when empty.
             */
            public function restore(int|string $id, array $data = []): Task
            {
                return $this->write(Task::class, Method::Patch, $this->path($id, 'restore'), 'tasks.go', $data === [] ? null : $data, (string) $id);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'optional resource by uuid' => [fn(): Operation => methodOperation(OperationKind::Action, 'PATCH', 'tasks/{task_uuid}/accept', ['task_uuid'], ResponseKind::OptionalResource, body: BodyKind::Data, method: 'accept'), <<<'PHP'
            /**
             * PATCH /tasks/{task_uuid}/accept
             *
             * @param  array<string, mixed>  $data
             */
            public function accept(string $taskUuid, array $data): ?Task
            {
                return $this->writeOptional(Task::class, Method::Patch, $this->path($taskUuid, 'accept'), 'tasks.go', $data, $taskUuid);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'delete' => [fn(): Operation => methodOperation(OperationKind::Destroy, 'DELETE', 'tasks/{id}', ['id'], ResponseKind::NoContent, method: 'delete'), <<<'PHP'
            /**
             * DELETE /tasks/{id}
             */
            public function delete(int|string $id): void
            {
                $this->writeWithoutResponse(Method::Delete, $this->path($id), 'tasks.go');
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'no content with a body' => [fn(): Operation => methodOperation(OperationKind::Action, 'POST', 'tasks/merge', [], ResponseKind::NoContent, body: BodyKind::Data, method: 'merge'), <<<'PHP'
            /**
             * POST /tasks/merge
             *
             * @param  array<string, mixed>  $data
             */
            public function merge(array $data): void
            {
                $this->writeWithoutResponse(Method::Post, $this->path('merge'), 'tasks.go', $data);
            }
        PHP, ['Axyr\\Productive\\Http\\Method']],
    'raw read' => [fn(): Operation => methodOperation(OperationKind::Action, 'GET', 'tasks/{id}/export/pdf', ['id'], ResponseKind::Raw, method: 'pdf'), <<<'PHP'
            /**
             * GET /tasks/{id}/export/pdf
             */
            public function pdf(int|string $id): Response
            {
                return $this->raw(Method::Get, $this->path($id, 'export', 'pdf'), 'tasks.go');
            }
        PHP, ['Axyr\\Productive\\Http\\Response', 'Axyr\\Productive\\Http\\Method']],
    'raw write' => [fn(): Operation => methodOperation(OperationKind::Action, 'POST', 'tasks/{id}/export', ['id'], ResponseKind::Raw, body: BodyKind::Data, method: 'export'), <<<'PHP'
            /**
             * POST /tasks/{id}/export
             *
             * @param  array<string, mixed>  $data
             */
            public function export(int|string $id, array $data): Response
            {
                return $this->raw(Method::Post, $this->path($id, 'export'), 'tasks.go', $data, (string) $id);
            }
        PHP, ['Axyr\\Productive\\Http\\Response', 'Axyr\\Productive\\Http\\Method']],
    'read returning a resource' => [fn(): Operation => methodOperation(OperationKind::Action, 'GET', 'tasks/{id}/subtasks/{subtask_id}', ['id', 'subtask_id'], ResponseKind::OptionalResource, method: 'subtask'), <<<'PHP'
            /**
             * GET /tasks/{id}/subtasks/{subtask_id}
             */
            public function subtask(int|string $id, string $subtaskId): ?Task
            {
                return $this->fetchOne(Task::class, $this->path($id, 'subtasks', $subtaskId), 'tasks.go');
            }
        PHP, []],
    'bulk item' => [fn(): Operation => methodOperation(OperationKind::Action, 'POST', 'tasks/copy', [], ResponseKind::OptionalResource, input: methodInput(), body: BodyKind::BulkItem, method: 'copy'), <<<'PHP'
            /**
             * POST /tasks/copy
             *
             * @param  CreateTaskData|array<string, mixed>  $data
             */
            public function copy(CreateTaskData|array $data): ?Task
            {
                return $this->writeAsBulk(Task::class, Method::Post, $this->path('copy'), 'tasks.go', $data);
            }
        PHP, ['Axyr\\Productive\\Data\\Input\\CreateTaskData', 'Axyr\\Productive\\Http\\Method']],
]);

it('refuses operations it cannot generate', function (Closure $operation, string $message) {
    expect(fn() => resourceMethod($operation())->lines())->toThrow(RuntimeException::class, $message);
})->with([
    'bulk item without an optional resource' => [fn(): Operation => methodOperation(OperationKind::Action, 'POST', 'tasks/copy', [], ResponseKind::Resource, input: methodInput(), body: BodyKind::BulkItem, method: 'copy'), 'tasks.go: a bulk-document action must document an optional resource response.'],
    'action returning a collection' => [fn(): Operation => methodOperation(OperationKind::Action, 'POST', 'tasks/list', [], ResponseKind::Collection, body: BodyKind::Data, method: 'listAll'), 'tasks.go: actions returning a collection are not supported yet.'],
]);
