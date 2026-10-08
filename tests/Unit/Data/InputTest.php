<?php

declare(strict_types=1);

use Axyr\Productive\Data\Input\CopyTaskData;
use Axyr\Productive\Data\Input\CreateTaskData;
use Axyr\Productive\Data\Input\CreateTimeEntryData;
use Axyr\Productive\Data\Input\MoveDependentTaskData;
use Axyr\Productive\Data\Input\RepositionTaskData;
use Axyr\Productive\Data\Input\UpdateTaskData;
use Axyr\Productive\Data\Input\UpdateTimeEntryData;
use Axyr\Productive\Data\InputData;
use Axyr\Productive\Data\Undefined;

enum InputTestColor: string
{
    case Red = 'red';
}

final readonly class InputTestData extends InputData
{
    public function __construct(
        public mixed $value = Undefined::Value,
        public DateTimeInterface|string|Undefined|null $date = Undefined::Value,
        public DateTimeInterface|string|Undefined|null $time = Undefined::Value,
    ) {}

    public function toAttributes(): array
    {
        return self::filter(['value' => $this->value, 'date' => self::date($this->date), 'time' => self::time($this->time)]);
    }
}

it('leaves out undefined fields and keeps explicit nulls', function () {
    expect((new InputTestData())->toAttributes())->toBe([])
        ->and((new InputTestData(value: null))->toAttributes())->toBe(['value' => null]);
});

it('serializes enums, date-times and nested arrays', function () {
    $dateTime = new DateTimeImmutable('2026-03-15T09:00:00+01:00');

    expect((new InputTestData(value: InputTestColor::Red))->toAttributes())->toBe(['value' => 'red'])
        ->and((new InputTestData(value: $dateTime))->toAttributes())->toBe(['value' => '2026-03-15T09:00:00+01:00'])
        ->and((new InputTestData(value: ['a' => InputTestColor::Red, 'b' => [$dateTime]]))->toAttributes())
        ->toBe(['value' => ['a' => 'red', 'b' => ['2026-03-15T09:00:00+01:00']]]);
});

it('formats date and time fields', function () {
    $dateTime = new DateTimeImmutable('2026-03-15T09:30:00+01:00');

    expect((new InputTestData(date: $dateTime, time: $dateTime))->toAttributes())->toBe(['date' => '2026-03-15', 'time' => '09:30'])
        ->and((new InputTestData(date: '2026-01-01', time: '10:00'))->toAttributes())->toBe(['date' => '2026-01-01', 'time' => '10:00'])
        ->and((new InputTestData(date: null, time: null))->toAttributes())->toBe(['date' => null, 'time' => null]);
});

it('builds the task create attributes', function () {
    $data = new CreateTaskData(
        title: 'Draft Q3 launch plan',
        projectId: 31002,
        taskListId: '7781',
        assigneeId: 5634,
        dueDate: new DateTimeImmutable('2026-06-15'),
        dueTime: '17:00',
        repeatOnDate: null,
        startDate: '2026-06-01',
        tagList: ['launch'],
        workflowStatusId: 101,
    );

    expect($data->toAttributes())->toBe([
        'title' => 'Draft Q3 launch plan',
        'project_id' => 31002,
        'task_list_id' => '7781',
        'assignee_id' => 5634,
        'due_date' => '2026-06-15',
        'due_time' => '17:00',
        'repeat_on_date' => null,
        'start_date' => '2026-06-01',
        'tag_list' => ['launch'],
        'workflow_status_id' => 101,
    ]);
});

it('maps every task input field to its API name', function () {
    $data = new UpdateTaskData(
        assigneeId: 1,
        attachmentIds: [2],
        customFields: ['3' => 'x'],
        description: 'd',
        dueDate: '2026-01-01',
        dueTime: '09:00',
        initialEstimate: 60,
        parentTaskId: 4,
        private: true,
        projectId: 5,
        remainingTime: 30,
        repeatOnDate: '2026-02-01',
        repeatOnInterval: 1,
        repeatOnMonthday: 15,
        repeatOnWeekday: [1, 3],
        repeatScheduleId: 2,
        serviceId: 6,
        skipReposition: false,
        startDate: '2026-01-02',
        subscriberIds: [7],
        tagList: ['t'],
        taskListId: 8,
        title: 'T',
        typeId: 1,
        workflowStatusId: 9,
    );

    expect(array_keys($data->toAttributes()))->toBe([
        'assignee_id', 'attachment_ids', 'custom_fields', 'description', 'due_date', 'due_time', 'initial_estimate',
        'parent_task_id', 'private', 'project_id', 'remaining_time', 'repeat_on_date', 'repeat_on_interval',
        'repeat_on_monthday', 'repeat_on_weekday', 'repeat_schedule_id', 'service_id', 'skip_reposition', 'start_date',
        'subscriber_ids', 'tag_list', 'task_list_id', 'title', 'type_id', 'workflow_status_id',
    ])->and((new UpdateTaskData())->toAttributes())->toBe([]);
});

it('builds the task action attributes', function () {
    expect((new CopyTaskData(title: 'Copy', templateId: 1, projectId: 2, taskListId: 3, workflowStatusId: 4, private: false, copyAsTaskTemplate: true, parentTaskId: null, templateDescription: 'T'))->toAttributes())
        ->toBe(['title' => 'Copy', 'template_id' => 1, 'project_id' => 2, 'task_list_id' => 3, 'workflow_status_id' => 4, 'private' => false, 'copy_as_task_template' => true, 'parent_task_id' => null, 'template_description' => 'T'])
        ->and((new RepositionTaskData(moveAfterId: 555))->toAttributes())->toBe(['move_after_id' => 555])
        ->and((new RepositionTaskData(moveAfterId: 1, moveBeforeId: 2, subtask: true))->toAttributes())->toBe(['move_after_id' => 1, 'move_before_id' => 2, 'subtask' => true])
        ->and((new MoveDependentTaskData(daysCount: -2, skipRootTask: true))->toAttributes())->toBe(['days_count' => -2, 'skip_root_task' => true]);
});

it('builds the time entry attributes', function () {
    $create = new CreateTimeEntryData(
        serviceId: 1856422,
        personId: 12,
        date: new DateTimeImmutable('2026-03-15'),
        time: 480,
        note: 'Auth flow',
        startedAt: new DateTimeImmutable('2026-03-15T09:00:00+00:00'),
        taskId: 120501,
    );

    expect($create->toAttributes())->toBe([
        'service_id' => 1856422,
        'person_id' => 12,
        'date' => '2026-03-15',
        'time' => 480,
        'note' => 'Auth flow',
        'started_at' => '2026-03-15T09:00:00+00:00',
        'task_id' => 120501,
    ]);

    $update = new UpdateTimeEntryData(
        billableTime: 1,
        calendarEventId: 'c',
        date: '2026-01-01',
        jiraIssueId: 'J-1',
        jiraIssueStatus: 'open',
        jiraIssueSummary: 's',
        jiraOrganization: 'o',
        jiraWorklogId: 'w',
        note: 'n',
        personId: 2,
        serviceId: 3,
        startedAt: '2026-01-01T10:00:00Z',
        taskId: 4,
        time: 5,
        useSalaryCurrency: true,
    );

    expect(array_keys($update->toAttributes()))->toBe([
        'billable_time', 'calendar_event_id', 'date', 'jira_issue_id', 'jira_issue_status', 'jira_issue_summary',
        'jira_organization', 'jira_worklog_id', 'note', 'person_id', 'service_id', 'started_at', 'task_id', 'time',
        'use_salary_currency',
    ]);
});

it('keeps all optional create fields undefined by default', function () {
    expect((new CreateTimeEntryData(serviceId: 1, personId: 2, date: '2026-01-01', time: 60))->toAttributes())
        ->toBe(['service_id' => 1, 'person_id' => 2, 'date' => '2026-01-01', 'time' => 60]);
});
