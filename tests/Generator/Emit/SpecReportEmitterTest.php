<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\SpecReportEmitter;
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
use Axyr\Productive\Generator\RecordedTypes;

function reportOperation(string $key, string $http, BodyKind $body = BodyKind::None, ?Input $input = null, bool $bulk = false): Operation
{
    [$method, $path] = explode(' ', $http);

    return new Operation($key, 'x', OperationKind::Action, $method, $path, [], ResponseKind::Resource, $bulk, true, $key, $input, body: $body);
}

function reportApi(): Api
{
    $input = new Input('CreateTaskData', 'task', [new Attribute('project_id', 'projectId', AttributeType::Int), new Attribute('title', 'title', AttributeType::String)]);
    $task = new Model('Task', 'tasks', '', [
        new Attribute('title', 'title', AttributeType::String),
        new Attribute('draft', 'draft', AttributeType::Bool, specType: AttributeType::Int),
        new Attribute('level', 'level', AttributeType::Int, undocumented: true),
    ], [new Relationship('project', false, 'projects'), new Relationship('todos', true, 'todos', undocumented: true)], unseen: ['url']);
    $plain = new Model('Plain', 'plains', '', [new Attribute('name', 'name', AttributeType::String)], []);

    return new Api([
        new Resource('tasks', 'TaskResource', 'X', 'tasks', 'Task', [
            reportOperation('tasks.index', 'GET tasks'),
            reportOperation('tasks.create', 'POST tasks', BodyKind::Attributes, $input),
            reportOperation('tasks.create_bulk', 'POST tasks', BodyKind::Attributes, $input, bulk: true),
            reportOperation('tasks.copy', 'POST tasks/copy', BodyKind::OptionalData),
            reportOperation('tasks.update', 'PATCH tasks/{id}', BodyKind::Data),
            reportOperation('tasks.merge', 'PATCH tasks/merge', BodyKind::Data),
            reportOperation('tasks.close', 'PATCH tasks/{id}/close'),
            reportOperation('tasks.low', 'PATCH tasks/{id}/low', BodyKind::Data),
            reportOperation('tasks.first', 'PATCH tasks/{id}/first', BodyKind::Data),
            reportOperation('tasks.last', 'PATCH tasks/{id}/last', BodyKind::Data),
            reportOperation('tasks.high', 'PATCH tasks/{id}/high', BodyKind::Data),
            reportOperation('tasks.unrecorded', 'PATCH tasks/{id}/unrecorded', BodyKind::Data),
        ]),
        new Resource('tasks/archive', 'ArchiveResource', 'X', 'tasks', 'Task', []),
        new Resource('reports/time_reports', 'TimeReportResource', 'X', 'time_reports', null, []),
        new Resource('plains', 'PlainResource', 'X', 'plains', 'Plain', []),
    ], [$plain, $task]);
}

function reportEmitter(?RecordedTypes $recorded = null, array $findings = ['written' => '2026-10-25 to 2026-11-02', 'findings' => []]): SpecReportEmitter
{
    $recorded ??= new RecordedTypes([
        'recorded_on' => '2026-11-02',
        'required' => ['tasks.create' => ['project', 'due'], 'tasks.create_bulk' => ['title'], 'ghosts.create' => ['x']],
        'statuses' => [
            'tasks.index' => 200,
            'tasks.copy' => 201,
            'tasks.update' => 200,
            'tasks.merge' => 422,
            'tasks.close' => 200,
            'tasks.low' => 199,
            'tasks.first' => 200,
            'tasks.last' => 299,
            'tasks.high' => 300,
            'undocumented.meetings' => 200,
            'undocumented.reports.purchase_order_reports' => 200,
            'undocumented.secrets' => 404,
            'meetings' => 200,
        ],
        'rejected_includes' => ['tasks.index' => ['owner', 'parent'], 'ghosts.index' => ['x']],
    ]);
    $recipes = [
        'tasks' => ['create' => ['title' => 'SDK check'], 'update' => ['title' => 'SDK check, renamed'], 'actions' => ['close' => ['note' => 'x'], 'merge' => ['winner' => 1], 'vanish' => ['x' => 1], 'low' => ['a' => 1], 'first' => ['b' => 1], 'last' => ['c' => 1], 'high' => ['d' => 1], 'unrecorded' => ['e' => 1]]],
        'tasks#copy' => ['create_with' => 'copy', 'create' => ['template_id' => 1]],
        'tasks#copy_again' => ['create_with' => 'copy', 'create' => ['template_id' => 1, 'name' => 'SDK check']],
        'tasks#empty' => ['create_with' => 'copy', 'create' => []],
        'tasks#on' => ['on' => 'tasks', 'actions' => 'close'],
        'tasks#odd' => ['create_with' => 5, 'create' => ['title' => 'x']],
    ];

    return new SpecReportEmitter(reportApi(), ['tasks', 'tasks/archive', 'reports/time_reports', 'plains', 'unknown'], $recorded, ['CreateTaskData' => ['title'], 'CreateGhostData' => ['x']], ['boards' => 'Gone.'], $recipes, $findings);
}

it('reports every difference with its endpoint and how to reproduce it', function () {
    $findings = ['written' => '2026-10-25 to 2026-11-02', 'findings' => [
        'Routes the API does not have' => [['endpoint' => '`GET /agent_roles`', 'spec' => 'Documents it.', 'api' => '404.', 'reproduce' => 'GET it.']],
        'Nothing here' => [],
    ]];

    expect(reportEmitter(findings: $findings)->emit())->toBe(<<<'MD'
        # OpenAPI spec report

        <!-- Generated by `composer generate` from generator/recorded-types.json and generator/config. Do not edit by hand. -->

        Where the Productive API v2 and its OpenAPI spec disagree, found while generating a PHP SDK from the spec. Reads were recorded on 2026-11-02, and writes on 2026-10-25 to 2026-11-02, against a trial organization. Every entry says how to reproduce it; `{id}` stands for the ID of any record of the organization.

        ## Required attributes the spec does not mark

        The request answers 422 "can't be blank" without them. "Undocumented" means the spec does not list the attribute at all.

        | Endpoint | Attributes | Reproduce |
        | --- | --- | --- |
        | `POST /tasks` | `due` (undocumented), `project_id`, `title` | Leave them out: 422 "can't be blank" for each. |

        ## Bodies the spec does not document

        The spec documents these operations without a request body. They accept these attributes.

        | Endpoint | Attributes | Reproduce |
        | --- | --- | --- |
        | `PATCH /tasks/{id}/first` | `b` | Send these attributes: 200. |
        | `PATCH /tasks/{id}/last` | `c` | Send these attributes: 299. |
        | `PATCH /tasks/{id}` | `title` | Send these attributes: 200. |
        | `POST /tasks/copy` | `template_id`, `name` | Send these attributes: 201. |

        ## Types that differ from the spec

        Every recorded value had the recorded type, never the spec's.

        | Endpoint | Attribute | Spec | Recorded |
        | --- | --- | --- | --- |
        | `GET /tasks` | `draft` | int | bool |

        ## Undocumented attributes

        Recorded responses carry them; the spec does not list them.

        | Endpoint | Attributes |
        | --- | --- |
        | `GET /tasks` | `level` |

        ## Undocumented relationships

        Recorded responses carry them with data; the spec does not list them.

        | Endpoint | Relationships |
        | --- | --- |
        | `GET /tasks` | `todos` |

        ## Example-only attributes

        The spec's example has them, its schema does not, and no recorded response carried them.

        | Endpoint | Attributes |
        | --- | --- |
        | `GET /tasks` | `url` |

        ## Includes the API rejects

        The spec documents these relationships; including them answers 400 "Unsupported include".

        | Endpoint | Relationships | Reproduce |
        | --- | --- | --- |
        | `GET /tasks` | `owner`, `parent` | `GET /tasks?include=owner`: 400. |

        ## Removed from the API

        The spec still documents them.

        | Resource, relationship or operation | What the API does |
        | --- | --- |
        | `boards` | Gone. |

        ## Resources outside the spec

        The official Ruby client knows them and the API answers 200; the spec does not document them.

        | Endpoint |
        | --- |
        | `GET /meetings` |
        | `GET /reports/purchase_order_reports` |

        ## Routes the API does not have

        Found by hand while recording and generating: the recordings alone do not show them.

        | Endpoint | Spec | API | Reproduce |
        | --- | --- | --- | --- |
        | `GET /agent_roles` | Documents it. | 404. | GET it. |

        MD);
});

it('leaves out every section without entries', function () {
    $report = (new SpecReportEmitter(reportApi(), [], new RecordedTypes(['recorded_on' => '2026-11-02']), [], [], [], ['written' => '2026-11-02', 'findings' => []]))->emit();

    expect($report)->not->toContain('##')
        ->and($report)->toEndWith("against a trial organization. Every entry says how to reproduce it; `{id}` stands for the ID of any record of the organization.\n");
});

it('documents no attributes for a body of data, optional data or nothing else', function () {
    expect(array_map(fn(BodyKind $kind): bool => $kind->documentsNoAttributes(), BodyKind::cases()))
        ->toBe(array_map(fn(BodyKind $kind): bool => in_array($kind, [BodyKind::Data, BodyKind::OptionalData], true), BodyKind::cases()))
        ->and(BodyKind::Data->documentsNoAttributes())->toBeTrue()
        ->and(BodyKind::Attributes->documentsNoAttributes())->toBeFalse();
});
