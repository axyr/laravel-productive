<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources;

use Axyr\Productive\Data\Input\CopyTaskData;
use Axyr\Productive\Data\Input\CreateTaskData;
use Axyr\Productive\Data\Input\MoveDependentTaskData;
use Axyr\Productive\Data\Input\RepositionTaskData;
use Axyr\Productive\Data\Input\UpdateTaskData;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Query\Query;

/**
 * Tasks: the assignable work items inside a project's task lists.
 *
 * @see https://developer.productive.io/reference/resources/tasks
 */
final class TaskResource extends Resource
{
    protected const string TYPE = 'tasks';

    protected const string PATH = 'tasks';

    /**
     * GET /tasks
     *
     * @return PendingQuery<Task>
     */
    public function query(): PendingQuery
    {
        return $this->newQuery(Task::class, 'tasks.index');
    }

    /**
     * GET /tasks/{id}
     *
     * @param  list<string>  $include
     */
    public function find(int|string $id, array $include = []): Task
    {
        return $this->fetchOne(Task::class, $this->path($id), 'tasks.show', (new Query())->include(...$include));
    }

    /**
     * POST /tasks
     *
     * @param  CreateTaskData|array<string, mixed>  $data
     */
    public function create(CreateTaskData|array $data): Task
    {
        return $this->write(Task::class, Method::Post, $this->path(), 'tasks.create', $data);
    }

    /**
     * PATCH /tasks/{id}
     *
     * @param  UpdateTaskData|array<string, mixed>  $data
     */
    public function update(int|string $id, UpdateTaskData|array $data): Task
    {
        return $this->write(Task::class, Method::Patch, $this->path($id), 'tasks.update', $data, (string) $id);
    }

    /**
     * DELETE /tasks/{id}
     */
    public function delete(int|string $id): void
    {
        $this->writeWithoutResponse(Method::Delete, $this->path($id), 'tasks.destroy');
    }

    /**
     * POST /tasks/copy
     *
     * @param  CopyTaskData|array<string, mixed>  $data
     */
    public function copy(CopyTaskData|array $data): Task
    {
        return $this->write(Task::class, Method::Post, $this->path('copy'), 'tasks.copy', $data);
    }

    /**
     * PATCH /tasks/{id}/reposition
     *
     * @param  RepositionTaskData|array<string, mixed>  $data
     */
    public function reposition(int|string $id, RepositionTaskData|array $data): void
    {
        $this->writeWithoutResponse(Method::Patch, $this->path($id, 'reposition'), 'tasks.reposition', $data, (string) $id);
    }

    /**
     * PATCH /tasks/{id}/move_dependent
     *
     * @param  MoveDependentTaskData|array<string, mixed>  $data
     */
    public function moveDependent(int|string $id, MoveDependentTaskData|array $data): Task
    {
        return $this->write(Task::class, Method::Patch, $this->path($id, 'move_dependent'), 'tasks.move_dependent', $data, (string) $id);
    }
}
