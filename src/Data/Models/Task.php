<?php

declare(strict_types=1);

namespace Axyr\Productive\Data\Models;

use Axyr\Productive\Data\Attributes;
use Axyr\Productive\Data\Model;
use DateTimeImmutable;

/**
 * An assignable work item inside a project's task list.
 *
 * @see https://developer.productive.io/reference/resources/tasks
 *
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
final readonly class Task extends Model
{
    public const string TYPE = 'tasks';

    /** Total billable hours tracked on this task (time + correction for billable services, zero for non-billable). */
    public ?int $billableTime;

    /** Number of tasks this task is blocking from starting. */
    public ?int $blockingDependencyCount;

    /** Total time booked against this task through resource scheduling, in minutes. Aggregated from the task's service bookings. */
    public ?int $bookedTime;

    /** Number of scheduled bookings referencing this task. */
    public ?int $bookingsCount;

    /** Whether the task is closed (derived from closed_at). */
    public ?bool $closed;

    /** Timestamp when the task was closed, or null if still open. */
    public ?DateTimeImmutable $closedAt;

    /** Timestamp when the task was created. */
    public ?DateTimeImmutable $createdAt;

    /** How the task was created (e.g. manually, via automation, via email). */
    public ?int $creationMethodId;

    /**
     * Custom field values set on this task.
     *
     * @var array<array-key, mixed>|null
     */
    public ?array $customFields;

    /** Timestamp if the task was soft-deleted, null otherwise. */
    public ?DateTimeImmutable $deletedAt;

    public ?string $description;

    /** Date by which the task should be completed. */
    public ?DateTimeImmutable $dueDate;

    /** Time of day for the due date deadline (HH:MM format). */
    public ?string $dueTime;

    /** Unique key for creating comments on this task via email. */
    public ?string $emailKey;

    /** Time booked against this task that is scheduled from today onward, in minutes — the forward-looking portion of `booked_time`. */
    public ?int $futureBookedTime;

    /** Originally forecasted time needed to complete the task, in minutes. */
    public ?int $initialEstimate;

    /** Timestamp of the most recent activity on this task. */
    public ?DateTimeImmutable $lastActivityAt;

    /** Number of tasks linked to this one (informational, no blocking). */
    public ?int $linkedDependencyCount;

    /** Short numeric identifier (alias for task_number). */
    public ?string $number;

    /** Number of unclosed subtasks. */
    public ?int $openSubtaskCount;

    /** Number of uncompleted to-do items. */
    public ?int $openTodoCount;

    /** Sort position of this task within its task list. */
    public ?int $placement;

    /** Whether this task is visible only to project members (not shared via public links). */
    public ?bool $private;

    /** Current projection of time still needed, decreases as time is tracked or can be adjusted manually. */
    public ?int $remainingTime;

    /** Specific date for date-based recurring tasks. */
    public ?DateTimeImmutable $repeatOnDate;

    /** How frequently the task repeats (e.g. daily, weekly, monthly). */
    public ?int $repeatOnInterval;

    /** Day of the month for monthly recurring tasks. */
    public ?int $repeatOnMonthday;

    /**
     * Array of ISO weekday IDs (1..7) on which a recurring task fires. Example: `[1, 3, 5]` for Mondays, Wednesdays, and Fridays.
     *
     * @var list<mixed>|null
     */
    public ?array $repeatOnWeekday;

    /** ID of the original task that spawned this recurring instance. */
    public ?int $repeatOriginId;

    /** ID of the repeat schedule if this task recurs on a regular interval. */
    public ?int $repeatScheduleId;

    /** Date when work on the task is planned to begin. */
    public ?DateTimeImmutable $startDate;

    /** Total number of subtasks under this task. */
    public ?int $subtaskCount;

    /** Sort position among sibling subtasks under the parent task. */
    public ?int $subtaskPlacement;

    /**
     * Tags applied to this task.
     *
     * @var list<mixed>|null
     */
    public ?array $tagList;

    /** Total number of dependency relationships on this task. */
    public ?int $taskDependencyCount;

    /** Unique number identifying a task within the organization. */
    public ?string $taskNumber;

    /** Descriptive name of the task. */
    public ?string $title;

    /**
     * IDs of people assigned to open to-do items on this task.
     *
     * @var array<array-key, mixed>|null
     */
    public ?array $todoAssigneeIds;

    /** Total number of to-do checklist items on this task. */
    public ?int $todoCount;

    /** Task type: regular task, subtask, or milestone. */
    public ?int $typeId;

    /** Timestamp when the task was last modified. */
    public ?DateTimeImmutable $updatedAt;

    /** Number of tasks this task is waiting on before it can proceed. */
    public ?int $waitingOnDependencyCount;

    /** Total time tracked on this task across all time entries, in minutes. */
    public ?int $workedTime;

    protected function hydrate(Attributes $attributes): void
    {
        $this->billableTime = $attributes->int('billable_time');
        $this->blockingDependencyCount = $attributes->int('blocking_dependency_count');
        $this->bookedTime = $attributes->int('booked_time');
        $this->bookingsCount = $attributes->int('bookings_count');
        $this->closed = $attributes->bool('closed');
        $this->closedAt = $attributes->dateTime('closed_at');
        $this->createdAt = $attributes->dateTime('created_at');
        $this->creationMethodId = $attributes->int('creation_method_id');
        $this->customFields = $attributes->object('custom_fields');
        $this->deletedAt = $attributes->dateTime('deleted_at');
        $this->description = $attributes->string('description');
        $this->dueDate = $attributes->date('due_date');
        $this->dueTime = $attributes->string('due_time');
        $this->emailKey = $attributes->string('email_key');
        $this->futureBookedTime = $attributes->int('future_booked_time');
        $this->initialEstimate = $attributes->int('initial_estimate');
        $this->lastActivityAt = $attributes->dateTime('last_activity_at');
        $this->linkedDependencyCount = $attributes->int('linked_dependency_count');
        $this->number = $attributes->string('number');
        $this->openSubtaskCount = $attributes->int('open_subtask_count');
        $this->openTodoCount = $attributes->int('open_todo_count');
        $this->placement = $attributes->int('placement');
        $this->private = $attributes->bool('private');
        $this->remainingTime = $attributes->int('remaining_time');
        $this->repeatOnDate = $attributes->date('repeat_on_date');
        $this->repeatOnInterval = $attributes->int('repeat_on_interval');
        $this->repeatOnMonthday = $attributes->int('repeat_on_monthday');
        $this->repeatOnWeekday = $attributes->list('repeat_on_weekday');
        $this->repeatOriginId = $attributes->int('repeat_origin_id');
        $this->repeatScheduleId = $attributes->int('repeat_schedule_id');
        $this->startDate = $attributes->date('start_date');
        $this->subtaskCount = $attributes->int('subtask_count');
        $this->subtaskPlacement = $attributes->int('subtask_placement');
        $this->tagList = $attributes->list('tag_list');
        $this->taskDependencyCount = $attributes->int('task_dependency_count');
        $this->taskNumber = $attributes->string('task_number');
        $this->title = $attributes->string('title');
        $this->todoAssigneeIds = $attributes->object('todo_assignee_ids');
        $this->todoCount = $attributes->int('todo_count');
        $this->typeId = $attributes->int('type_id');
        $this->updatedAt = $attributes->dateTime('updated_at');
        $this->waitingOnDependencyCount = $attributes->int('waiting_on_dependency_count');
        $this->workedTime = $attributes->int('worked_time');
    }

    public function project(): ?Model
    {
        return $this->belongsTo('project', Model::class);
    }

    public function taskList(): ?Model
    {
        return $this->belongsTo('task_list', Model::class);
    }

    public function workflowStatus(): ?Model
    {
        return $this->belongsTo('workflow_status', Model::class);
    }

    public function assignee(): ?Model
    {
        return $this->belongsTo('assignee', Model::class);
    }

    public function creator(): ?Model
    {
        return $this->belongsTo('creator', Model::class);
    }

    public function lastActor(): ?Model
    {
        return $this->belongsTo('last_actor', Model::class);
    }

    public function service(): ?Model
    {
        return $this->belongsTo('service', Model::class);
    }

    public function parentTask(): ?Task
    {
        return $this->belongsTo('parent_task', Task::class);
    }

    public function repeatedTask(): ?Task
    {
        return $this->belongsTo('repeated_task', Task::class);
    }

    public function templateObject(): ?Model
    {
        return $this->belongsTo('template_object', Model::class);
    }

    public function organization(): ?Model
    {
        return $this->belongsTo('organization', Model::class);
    }

    /**
     * @return list<Model>
     */
    public function attachments(): array
    {
        return $this->hasMany('attachments', Model::class);
    }

    /**
     * @return list<Model>
     */
    public function customFieldPeople(): array
    {
        return $this->hasMany('custom_field_people', Model::class);
    }

    /**
     * @return list<Model>
     */
    public function customFieldAttachments(): array
    {
        return $this->hasMany('custom_field_attachments', Model::class);
    }
}
