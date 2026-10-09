<?php

declare(strict_types=1);

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\Recording\Recorder;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

/**
 * A connector that answers from a closure and remembers every request.
 */
final class RecordingTestConnector implements ConnectorInterface
{
    /** @var list<Request> */
    public array $sent = [];

    /**
     * @param  Closure(Request): Response  $respond
     */
    public function __construct(private readonly Closure $respond) {}

    public function send(Request $request): Response
    {
        $this->sent[] = $request;
        $response = ($this->respond)($request);

        return $response->successful() ? $response : throw ApiException::fromResponse($request, $response);
    }
}

function recordingOperation(string $key, OperationKind $kind, string $path, string $method = 'GET', bool $organization = true): Operation
{
    return new Operation($key, 'x', $kind, $method, $path, str_contains($path, '{id}') ? ['id'] : [], ResponseKind::Resource, false, $organization, $key);
}

/**
 * @param  list<Operation>  $operations
 */
function recordingResource(string $path, array $operations, ?string $model = null, bool $reports = false): Resource
{
    return new Resource($path, 'X', 'X', $path, $model, $operations, reportRateLimit: $reports);
}

/**
 * @param  list<string>  $relationships
 */
function recordingModel(string $class, array $relationships): Model
{
    return new Model($class, 'tasks', '', [], array_map(fn(string $name): Relationship => new Relationship($name, false, null), $relationships));
}

function jsonResponse(int $status, mixed $body, array $headers = []): Response
{
    return new Response($status, $headers, json_encode($body, JSON_THROW_ON_ERROR));
}

it('records the index, the first record and the index with every relationship included', function () {
    $api = new Api([recordingResource('tasks', [
        recordingOperation('tasks.index', OperationKind::Index, 'tasks'),
        recordingOperation('tasks.create', OperationKind::Create, 'tasks', 'POST'),
        recordingOperation('tasks.show', OperationKind::Show, 'tasks/{id}'),
    ], 'Task')], [recordingModel('Task', ['assignee', 'project'])]);
    $connector = new RecordingTestConnector(fn(Request $request): Response => jsonResponse(200, ['data' => [['id' => 7, 'type' => 'tasks']]], ['X-RateLimit-Remaining' => ['99'], 'Content-Type' => ['application/vnd.api+json'], 'Set-Cookie' => ['x']]));

    $recordings = iterator_to_array((new Recorder($api, $connector))->record());

    expect(array_keys($recordings))->toBe(['tasks.index', 'tasks.show', 'tasks.index.include'])
        ->and($recordings['tasks.index'])->toBe([
            'request' => ['method' => 'GET', 'path' => 'tasks', 'query' => 'page[size]=20'],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/vnd.api+json', 'X-RateLimit-Remaining' => '99'],
            'body' => ['data' => [['id' => 7, 'type' => 'tasks']]],
        ])
        ->and($recordings['tasks.show']['request'])->toBe(['method' => 'GET', 'path' => 'tasks/7', 'query' => ''])
        ->and($recordings['tasks.index.include']['request']['query'])->toBe('page[size]=5&include=assignee,project')
        ->and(array_map(fn(Request $request): string => $request->method->value . ' ' . $request->operation, $connector->sent))
        ->toBe(['GET tasks.index', 'GET tasks.show', 'GET tasks.index']);
});

it('splits the include list until every relationship that cannot be included is isolated', function () {
    $api = new Api([recordingResource('tasks', [recordingOperation('tasks.index', OperationKind::Index, 'tasks')], 'Task')], [recordingModel('Task', ['a', 'b', 'c', 'd'])]);
    $includes = fn(Request $request): array => explode(',', explode('include=', $request->query . 'include=')[1]);
    $connector = new RecordingTestConnector(fn(Request $request): Response => in_array('c', $includes($request), true)
        ? jsonResponse(400, ['errors' => [['status' => '400', 'title' => 'Invalid include']]])
        : jsonResponse(200, ['data' => [['id' => '1', 'type' => 'tasks']]]));

    $recordings = iterator_to_array((new Recorder($api, $connector))->record());

    expect(array_map(fn(array $recording): string => $recording['status'] . ' ' . $recording['request']['query'], $recordings))->toBe([
        'tasks.index' => '200 page[size]=20',
        'tasks.index.include.a+b' => '200 page[size]=5&include=a,b',
        'tasks.index.include.c' => '400 page[size]=5&include=c',
        'tasks.index.include.d' => '200 page[size]=5&include=d',
    ])
        ->and(array_column($connector->sent, 'query'))->toBe([
            'page[size]=20',
            'page[size]=5&include=a,b,c,d',
            'page[size]=5&include=a,b',
            'page[size]=5&include=c,d',
            'page[size]=5&include=c',
            'page[size]=5&include=d',
        ]);
});

it('records error responses and stops after an empty or failed index', function (Response $response) {
    $api = new Api([recordingResource('tasks', [
        recordingOperation('tasks.index', OperationKind::Index, 'tasks'),
        recordingOperation('tasks.show', OperationKind::Show, 'tasks/{id}'),
    ], 'Task')], [recordingModel('Task', ['project'])]);
    $connector = new RecordingTestConnector(fn(): Response => $response);

    $recordings = iterator_to_array((new Recorder($api, $connector))->record());

    expect(array_keys($recordings))->toBe(['tasks.index'])
        ->and($recordings['tasks.index']['status'])->toBe($response->status)
        ->and($connector->sent)->toHaveCount(1);
})->with([
    'forbidden' => [jsonResponse(403, ['errors' => [['status' => '403', 'title' => 'Forbidden']]])],
    'empty' => [jsonResponse(200, ['data' => []])],
    'non-json' => [new Response(200, [], 'not json')],
    'id-less' => [jsonResponse(200, ['data' => [['type' => 'tasks']]])],
]);

it('keeps a body that is not JSON as a string', function () {
    $api = new Api([recordingResource('tasks', [recordingOperation('tasks.index', OperationKind::Index, 'tasks')])], []);

    $recordings = iterator_to_array((new Recorder($api, new RecordingTestConnector(fn(): Response => new Response(502, [], 'Bad gateway'))))->record());

    expect($recordings['tasks.index']['body'])->toBe('Bad gateway');
});

it('skips authentication, public links and resources without a GET index', function () {
    $api = new Api([
        recordingResource('passwords', [recordingOperation('passwords.index', OperationKind::Index, 'passwords')]),
        recordingResource('public/pages', [recordingOperation('public.pages.index', OperationKind::Index, 'public/pages', organization: false)]),
        recordingResource('sessions', [recordingOperation('sessions.index', OperationKind::Index, 'sessions')]),
        recordingResource('timers', [recordingOperation('timers.show', OperationKind::Show, 'timers/{id}'), recordingOperation('timers.create', OperationKind::Index, 'timers', 'POST')]),
    ], []);
    $connector = new RecordingTestConnector(fn(): Response => jsonResponse(200, ['data' => []]));

    expect(iterator_to_array((new Recorder($api, $connector))->record()))->toBe([])
        ->and($connector->sent)->toBe([]);
});

it('records a resource without a show or a model as its index only', function () {
    $api = new Api([recordingResource('reports/time_reports', [recordingOperation('reports.time_reports.index', OperationKind::Index, 'reports/time_reports')], 'TimeReport', reports: true)], []);
    $connector = new RecordingTestConnector(fn(): Response => jsonResponse(200, ['data' => [['id' => '1', 'type' => 'time_reports']]]));

    $recordings = iterator_to_array((new Recorder($api, $connector))->record());

    expect(array_keys($recordings))->toBe(['reports.time_reports.index'])
        ->and($connector->sent[0]->rateLimits)->toEqual([RateLimit::reports(), Recorder::pace()])
        ->and($connector->sent[0]->requiresOrganization)->toBeTrue();
});

it('sends public and organization-less requests as the operation says', function () {
    $api = new Api([recordingResource('organizations', [recordingOperation('organizations.index', OperationKind::Index, 'organizations', organization: false)])], []);
    $connector = new RecordingTestConnector(fn(): Response => jsonResponse(200, ['data' => []]));

    iterator_to_array((new Recorder($api, $connector))->record());

    expect($connector->sent[0]->requiresOrganization)->toBeFalse()
        ->and($connector->sent[0]->rateLimits)->toEqual([Recorder::pace()]);
});

it('probes undocumented resources for their status only', function () {
    $connector = new RecordingTestConnector(fn(Request $request): Response => $request->path === 'meetings'
        ? jsonResponse(200, ['data' => [['id' => '1', 'type' => 'meetings', 'attributes' => ['title' => 'secret']]]])
        : jsonResponse(404, ['errors' => [['status' => '404']]]));

    $recordings = iterator_to_array((new Recorder(new Api([], []), $connector, ['meetings', 'reports/new_time_reports']))->record());

    expect($recordings)->toBe([
        'undocumented.meetings' => ['request' => ['method' => 'GET', 'path' => 'meetings', 'query' => 'page[size]=1'], 'status' => 200],
        'undocumented.reports.new_time_reports' => ['request' => ['method' => 'GET', 'path' => 'reports/new_time_reports', 'query' => 'page[size]=1'], 'status' => 404],
    ]);
});

it('stops on the first 429 without recording it', function () {
    $api = new Api([
        recordingResource('a', [recordingOperation('a.index', OperationKind::Index, 'a')]),
        recordingResource('b', [recordingOperation('b.index', OperationKind::Index, 'b')]),
        recordingResource('c', [recordingOperation('c.index', OperationKind::Index, 'c')]),
    ], []);
    $connector = new RecordingTestConnector(fn(Request $request): Response => $request->path === 'b'
        ? jsonResponse(429, ['errors' => [['status' => '429']]])
        : jsonResponse(200, ['data' => []]));
    $recorded = [];

    expect(function () use ($api, $connector, &$recorded) {
        foreach ((new Recorder($api, $connector))->record() as $name => $recording) {
            $recorded[] = $name;
        }
    })->toThrow(RateLimitException::class);

    expect($recorded)->toBe(['a.index'])
        ->and($connector->sent)->toHaveCount(2);
});

it('redacts secrets in recorded bodies', function () {
    $api = new Api([recordingResource('webhooks', [recordingOperation('webhooks.index', OperationKind::Index, 'webhooks')])], []);
    $connector = new RecordingTestConnector(fn(): Response => jsonResponse(200, ['data' => [], 'meta' => ['secret' => 'abc']]));

    $recordings = iterator_to_array((new Recorder($api, $connector))->record());

    expect($recordings['webhooks.index']['body'])->toBe(['data' => [], 'meta' => ['secret' => '[redacted]']]);
});

it('paces every request to one per second', function () {
    expect(Recorder::pace())->toEqual(new RateLimit('recorder', 1, 1));
});

it('adds the configured filter to the index and include queries of a path', function () {
    $api = new Api([
        recordingResource('reports/time_reports', [recordingOperation('reports.time_reports.index', OperationKind::Index, 'reports/time_reports')], 'TimeReport', reports: true),
        recordingResource('tasks', [recordingOperation('tasks.index', OperationKind::Index, 'tasks')]),
    ], [recordingModel('TimeReport', ['person'])]);
    $connector = new RecordingTestConnector(fn(): Response => jsonResponse(200, ['data' => [['id' => '1', 'type' => 'time_reports']]]));

    iterator_to_array((new Recorder($api, $connector, filters: ['reports/time_reports' => 'filter[after]=2026-09-09&filter[before]=2026-10-09']))->record());

    expect(array_column($connector->sent, 'query'))->toBe([
        'filter[after]=2026-09-09&filter[before]=2026-10-09&page[size]=20',
        'filter[after]=2026-09-09&filter[before]=2026-10-09&page[size]=5&include=person',
        'page[size]=20',
    ]);
});
