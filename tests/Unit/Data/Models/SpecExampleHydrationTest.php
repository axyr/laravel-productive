<?php

declare(strict_types=1);

use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Data\Models\TimeEntry;
use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\JsonApi\Document;
use Tests\Support\SpecExamples;

function hydrateExample(string $operationId): mixed
{
    $document = Document::fromArray(SpecExamples::response($operationId));
    $registry = new ModelRegistry();

    return $document->isCollection()
        ? array_map(fn($resource) => $registry->hydrate($resource, $document->index()), $document->resources())
        : $registry->hydrate($document->resource(), $document->index());
}

it('hydrates the task example', function () {
    $task = hydrateExample('tasks-show');

    expect($task)->toBeInstanceOf(Task::class);
    /** @var Task $task */
    expect($task->id)->toBe('120501')
        ->and($task->title)->toBe('Implement user authentication')
        ->and($task->description)->toBe('Set up OAuth2 authentication with Google and GitHub providers')
        ->and($task->number)->toBe('42')
        ->and($task->taskNumber)->toBe('42')
        ->and($task->private)->toBeFalse()
        ->and($task->closed)->toBeFalse()
        ->and($task->closedAt)->toBeNull()
        ->and($task->dueDate?->format('Y-m-d'))->toBe('2026-03-31')
        ->and($task->startDate?->format('Y-m-d'))->toBe('2026-03-15')
        ->and($task->createdAt?->format(DATE_ATOM))->toBe('2026-01-15T10:00:00+00:00')
        ->and($task->tagList)->toBe(['backend', 'security'])
        ->and($task->customFields)->toBeNull()
        ->and($task->initialEstimate)->toBe(480)
        ->and($task->typeId)->toBe(1)
        ->and($task->placement)->toBe(1000000)
        ->and($task->subtaskPlacement)->toBeNull()
        ->and($task->relationshipId('project'))->toBe('6899')
        ->and($task->relationshipId('assignee'))->toBe('12')
        ->and($task->service())->toBeNull()
        ->and($task->parentTask())->toBeNull();
});

it('hydrates every task attribute the schema documents', function () {
    $task = new Task(Document::fromArray(['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => [
        'blocking_dependency_count' => 1, 'booked_time' => 2, 'bookings_count' => 3, 'creation_method_id' => 4,
        'custom_fields' => ['42' => 'x'], 'deleted_at' => '2026-01-01T00:00:00Z', 'due_time' => '17:00', 'email_key' => 'k',
        'future_booked_time' => 5, 'last_activity_at' => '2026-01-02T00:00:00Z', 'linked_dependency_count' => 6,
        'open_subtask_count' => 7, 'open_todo_count' => 8, 'remaining_time' => 9, 'repeat_on_date' => '2026-02-01',
        'repeat_on_interval' => 10, 'repeat_on_monthday' => 11, 'repeat_on_weekday' => [1, 3], 'repeat_origin_id' => 12,
        'repeat_schedule_id' => 2, 'subtask_count' => 13, 'task_dependency_count' => 14, 'todo_assignee_ids' => ['5' => [1]],
        'todo_count' => 15, 'updated_at' => '2026-01-03T00:00:00Z', 'waiting_on_dependency_count' => 16, 'worked_time' => 17,
        'billable_time' => 18,
    ]]])->resource());

    expect([
        $task->blockingDependencyCount, $task->bookedTime, $task->bookingsCount, $task->creationMethodId, $task->futureBookedTime,
        $task->linkedDependencyCount, $task->openSubtaskCount, $task->openTodoCount, $task->remainingTime, $task->repeatOnInterval,
        $task->repeatOnMonthday, $task->repeatOriginId, $task->repeatScheduleId, $task->subtaskCount, $task->taskDependencyCount,
        $task->todoCount, $task->waitingOnDependencyCount, $task->workedTime, $task->billableTime,
    ])->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 2, 13, 14, 15, 16, 17, 18])
        ->and($task->customFields)->toBe(['42' => 'x'])
        ->and($task->todoAssigneeIds)->toBe(['5' => [1]])
        ->and($task->repeatOnWeekday)->toBe([1, 3])
        ->and($task->dueTime)->toBe('17:00')
        ->and($task->emailKey)->toBe('k')
        ->and($task->deletedAt?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($task->lastActivityAt?->format('Y-m-d'))->toBe('2026-01-02')
        ->and($task->updatedAt?->format('Y-m-d'))->toBe('2026-01-03')
        ->and($task->repeatOnDate?->format('Y-m-d'))->toBe('2026-02-01');
});

it('hydrates the task collection example', function () {
    $tasks = hydrateExample('tasks-index');

    expect($tasks)->not->toBeEmpty()
        ->and($tasks)->each->toBeInstanceOf(Task::class);
});

it('hydrates the time entry example', function () {
    $entry = hydrateExample('time_entries-show');

    expect($entry)->toBeInstanceOf(TimeEntry::class);
    /** @var TimeEntry $entry */
    expect($entry->id)->toBe('84848214')
        ->and($entry->date?->format('Y-m-d'))->toBe('2026-03-15')
        ->and($entry->time)->toBe(480)
        ->and($entry->note)->toBe('Implemented user authentication flow')
        ->and($entry->trackMethodId)->toBe(1)
        ->and($entry->startedAt?->format(DATE_ATOM))->toBe('2026-03-15T09:00:00+00:00')
        ->and($entry->timerStartedAt)->toBeNull()
        ->and($entry->approved)->toBeFalse()
        ->and($entry->approvedAt)->toBeNull()
        ->and($entry->invoiced)->toBeFalse()
        ->and($entry->overhead)->toBeFalse()
        ->and($entry->rejected)->toBeFalse()
        ->and($entry->submitted)->toBeFalse()
        ->and($entry->currency)->toBe('USD')
        ->and($entry->relationshipId('person'))->toBe('12')
        ->and($entry->relationshipId('task'))->toBe('120501')
        ->and($entry->approver())->toBeNull();
});

it('hydrates the time report example', function () {
    $rows = hydrateExample('reports-time_reports-index');

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBeInstanceOf(TimeReport::class);
    /** @var TimeReport $row */
    $row = $rows[0];
    expect($row->billableTime)->toBe(14400.0)
        ->and($row->currency)->toBe('USD')
        ->and($row->attribute('total_time'))->toBe(16200);
});
