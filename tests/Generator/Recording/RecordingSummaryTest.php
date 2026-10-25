<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\RecordingSummary;

it('keeps the kinds of values and the relationships, never the values', function () {
    $summary = (new RecordingSummary())->summarize([
        'tasks.show' => ['status' => 200, 'body' => ['data' => [
            'type' => 'tasks',
            'id' => '7',
            'attributes' => ['title' => 'Secret plan', 'level' => 1, 'closed_at' => null],
            'relationships' => [
                'project' => ['data' => ['type' => 'projects', 'id' => '3']],
                'subtasks' => ['meta' => ['included' => false]],
            ],
        ]]],
        'tasks.index' => ['status' => 200, 'body' => [
            'data' => [
                ['type' => 'tasks', 'id' => '8', 'attributes' => ['title' => 'Other', 'closed_at' => '2026-10-09T10:00:00.000+02:00'], 'relationships' => [
                    'assignees' => ['data' => [['type' => 'people', 'id' => '1'], ['type' => 'users', 'id' => '2'], 'junk']],
                    'project' => ['data' => null],
                    'parent' => 'junk',
                ]],
                'junk',
                ['id' => '9'],
            ],
            'included' => [['type' => 'projects', 'id' => '3', 'attributes' => ['name' => 'Apollo']]],
        ]],
    ]);

    expect($summary)->toBe([
        'statuses' => ['tasks.index' => 200, 'tasks.show' => 200],
        'rejected_includes' => [],
        'required' => [],
        'types' => [
            'projects' => ['attributes' => ['name' => ['string']], 'relationships' => []],
            'tasks' => [
                'attributes' => ['closed_at' => ['date-time', 'null'], 'level' => ['int'], 'title' => ['string']],
                'relationships' => [
                    'assignees' => ['to_many' => true, 'types' => ['people', 'users']],
                    'parent' => ['to_many' => null, 'types' => []],
                    'project' => ['to_many' => false, 'types' => ['projects']],
                    'subtasks' => ['to_many' => null, 'types' => []],
                ],
            ],
        ],
    ])
        ->and(json_encode($summary))->not->toContain('Secret')->not->toContain('Apollo');
});

it('keeps a to-many relationship to-many once seen as a list', function () {
    $resource = fn(mixed $data): array => ['type' => 'tasks', 'id' => '1', 'relationships' => ['todos' => ['data' => $data]]];

    $summary = (new RecordingSummary())->summarize([
        'a' => ['status' => 200, 'body' => ['data' => [$resource([['type' => 'todos', 'id' => '1']]), $resource(['type' => 'todos', 'id' => '2'])]]],
    ]);

    expect($summary['types']['tasks']['relationships']['todos'])->toBe(['to_many' => true, 'types' => ['todos']]);
});

it('records rejected includes and ignores failed and malformed recordings', function () {
    $summary = (new RecordingSummary())->summarize([
        'reports.task_reports.index.include.board' => ['status' => 400, 'body' => ['errors' => [['title' => 'Unsupported include']]]],
        'reports.task_reports.index.include.organization' => ['status' => 400, 'body' => []],
        'reports.task_reports.index.include' => ['status' => 400, 'body' => []],
        'reports.task_reports.index.include.a+b' => ['status' => 400, 'body' => []],
        'skills.index' => ['status' => 403, 'body' => ['data' => [['type' => 'skills', 'id' => '1', 'attributes' => ['name' => 'x']]]]],
        'teams.index' => ['status' => 200, 'body' => 'not json'],
        'tags.index' => ['status' => 300, 'body' => ['data' => [['type' => 'tags', 'id' => '1']]]],
        'todos.index' => ['status' => 199, 'body' => ['data' => [['type' => 'todos', 'id' => '1']]]],
        'odd' => ['body' => []],
        'undocumented.meetings' => ['status' => 200],
        'emails.index' => ['status' => 200, 'body' => ['data' => [['type' => 'emails', 'id' => '1', 'attributes' => [1, 2], 'relationships' => [3]]]]],
    ]);

    expect($summary['rejected_includes'])->toBe(['reports.task_reports.index' => ['board', 'organization']])
        ->and($summary['statuses'])->toBe([
            'emails.index' => 200,
            'odd' => 0,
            'reports.task_reports.index.include' => 400,
            'reports.task_reports.index.include.a+b' => 400,
            'reports.task_reports.index.include.board' => 400,
            'reports.task_reports.index.include.organization' => 400,
            'skills.index' => 403,
            'tags.index' => 300,
            'teams.index' => 200,
            'todos.index' => 199,
            'undocumented.meetings' => 200,
        ])
        ->and($summary['types'])->toBe(['emails' => ['attributes' => [], 'relationships' => []]]);
});

it('observes every 2xx response, and lists kinds and types once and sorted', function () {
    $task = fn(mixed $due, array $types): array => ['type' => 'tasks', 'id' => '1', 'attributes' => ['due' => $due], 'relationships' => ['owner' => ['data' => array_map(fn(string $type): array => ['type' => $type, 'id' => '1'], $types)]]];

    $summary = (new RecordingSummary())->summarize([
        'a' => ['status' => 299, 'body' => ['data' => [$task('text', ['users', 'people']), $task(null, ['people'])]]],
        'b' => ['status' => 200, 'body' => ['data' => [$task(5, ['companies'])]]],
    ]);

    expect($summary['types']['tasks'])->toBe([
        'attributes' => ['due' => ['int', 'null', 'string']],
        'relationships' => ['owner' => ['to_many' => true, 'types' => ['companies', 'people', 'users']]],
    ]);
});

it('reads write runs by operation, the later status winning, and keeps what any run found blank', function () {
    $blank = fn(string ...$pointers): array => ['status' => 422, 'body' => ['errors' => array_map(fn(string $pointer): array => ['detail' => "can't be blank", 'source' => ['pointer' => $pointer]], $pointers)]];

    $summary = (new RecordingSummary())->summarize([
        'writes-2026-10-25-123700/001.widgets.create' => ['status' => 201, 'body' => ['data' => ['type' => 'widgets', 'id' => '1', 'attributes' => ['title' => 'x']]]],
        'writes-2026-10-25-122437/040.widgets.create' => $blank('data/attributes/row_position', '/data/attributes/column_position'),
        'writes-2026-10-25-122437/041.widgets.update' => $blank('data/attributes/title'),
        'writes-2026-10-25-123700/002.widgets.update' => $blank('data/attributes/row_position'),
        'writes-2026-10-25-122437/066.workflow_statuses.destroy' => $blank('data/attributes/target_workflow_status_id'),
        'writes-2026-10-25-122437/067.public/pages.create' => $blank('data/attributes/title'),
        '1.tasks.create' => ['status' => 422, 'body' => ['errors' => [
            ['detail' => 'is not included in the list', 'source' => ['pointer' => 'data/attributes/type']],
            ['detail' => "can't be blank", 'source' => ['pointer' => 'data/relationships/project']],
            ['detail' => "can't be blank", 'source' => ['parameter' => 'title']],
            ['detail' => "can't be blank", 'source' => ['pointer' => 'data/attributes/Title']],
            ['detail' => "can't be blank", 'meta' => ['if' => 'sales_pipeline?'], 'source' => ['pointer' => 'data/attributes/probability']],
            'junk',
        ]]],
        '2.tasks.update' => ['status' => 400, 'body' => ['errors' => [['detail' => "can't be blank", 'source' => ['pointer' => 'data/attributes/title']]]]],
        '3.tasks.archive' => ['status' => 422, 'body' => ['errors' => 'junk']],
        'tasks.index.include.project' => ['status' => 400],
    ]);

    expect($summary['statuses'])->toBe([
        'public/pages.create' => 422,
        'tasks.archive' => 422,
        'tasks.create' => 422,
        'tasks.index.include.project' => 400,
        'tasks.update' => 400,
        'widgets.create' => 201,
        'widgets.update' => 422,
        'workflow_statuses.destroy' => 422,
    ])
        ->and($summary['required'])->toBe([
            'public/pages.create' => ['title'],
            'widgets.create' => ['column_position', 'row_position'],
            'widgets.update' => ['row_position', 'title'],
            'workflow_statuses.destroy' => ['target_workflow_status_id'],
        ])
        ->and($summary['rejected_includes'])->toBe(['tasks.index' => ['project']])
        ->and($summary['types'])->toBe(['widgets' => ['attributes' => ['title' => ['string']], 'relationships' => []]]);
});
