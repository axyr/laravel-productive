<?php

declare(strict_types=1);

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\RateLimitException;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\Recording\WriteRecorder;
use Axyr\Productive\Generator\Recording\WriteStep;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;

/**
 * Answers from a closure, remembers every request and throws for a failed response, like the connector.
 */
final class WriteTestConnector implements ConnectorInterface
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

function writerStep(string $key, string $method, string $path, ?array $attributes, bool $onCreated = false, bool $creates = false, bool $cleanup = false): WriteStep
{
    $resource = substr($key, 0, (int) strpos($key, '.'));

    return new WriteStep(new Operation($key, 'x', OperationKind::Action, $method, $path, [], ResponseKind::Resource, false, true, $key), $resource, $resource, $attributes, $onCreated, $creates, $cleanup);
}

/**
 * @return list<WriteStep>
 */
function calendarSteps(): array
{
    return [
        writerStep('holiday_calendars.create', 'POST', 'holiday_calendars', ['name' => 'SDK check', 'subsidiary_id' => '{first:subsidiaries}'], creates: true),
        writerStep('holiday_calendars.update', 'PATCH', 'holiday_calendars/{id}', ['name' => 'SDK check 2'], onCreated: true),
        writerStep('holidays.create', 'POST', 'holidays', ['date' => '{date:+30}', 'holiday_calendar_id' => '{created:holiday_calendars}', 'tags' => ['{date:-1}', 'x']], creates: true),
        writerStep('holiday_calendars.merge', 'PATCH', 'holiday_calendars/merge', ['ids' => ['{created:holiday_calendars}']]),
        writerStep('holidays.destroy', 'DELETE', 'holidays/{id}', null, onCreated: true, cleanup: true),
        writerStep('holiday_calendars.destroy', 'DELETE', 'holiday_calendars/{id}', null, onCreated: true, cleanup: true),
    ];
}

function writerResponse(int $status, mixed $body = null): Response
{
    return new Response($status, ['Content-Type' => 'application/vnd.api+json'], $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
}

/**
 * Lists answer with one subsidiary; creates with an ID per resource; everything else 200 without data.
 */
function writerApi(): WriteTestConnector
{
    return new WriteTestConnector(fn(Request $request): Response => match (true) {
        $request->method->value === 'GET' => writerResponse(200, ['data' => [['id' => 7, 'type' => 'subsidiaries']]]),
        $request->path === 'holiday_calendars' => writerResponse(201, ['data' => ['id' => 11, 'type' => 'holiday_calendars']]),
        $request->path === 'holidays' => writerResponse(201, ['data' => ['id' => '22', 'type' => 'holidays']]),
        $request->method->value === 'DELETE' => writerResponse(204),
        default => writerResponse(200, ['data' => ['id' => '99', 'type' => 'holiday_calendars']]),
    });
}

it('shows a dry run with placeholders for what the run would create, reading only the lists it refers to', function () {
    $connector = writerApi();

    $recordings = iterator_to_array((new WriteRecorder($connector, '2025-01-15'))->run(calendarSteps(), execute: false));

    expect(array_keys($recordings))->toBe(['001.holiday_calendars.create', '002.holiday_calendars.update', '003.holidays.create', '004.holiday_calendars.merge', '005.holidays.destroy', '006.holiday_calendars.destroy'])
        ->and($recordings['001.holiday_calendars.create'])->toBe(['request' => ['method' => 'POST', 'path' => 'holiday_calendars', 'body' => ['data' => ['type' => 'holiday_calendars', 'attributes' => ['name' => 'SDK check', 'subsidiary_id' => '7']]]]])
        ->and($recordings['002.holiday_calendars.update']['request'])->toBe(['method' => 'PATCH', 'path' => 'holiday_calendars/{created:holiday_calendars}', 'body' => ['data' => ['type' => 'holiday_calendars', 'id' => '{created:holiday_calendars}', 'attributes' => ['name' => 'SDK check 2']]]])
        ->and($recordings['003.holidays.create']['request']['body']['data']['attributes'])->toBe(['date' => '2025-02-14', 'holiday_calendar_id' => '{created:holiday_calendars}', 'tags' => ['2025-01-14', 'x']])
        ->and($recordings['005.holidays.destroy'])->toBe(['request' => ['method' => 'DELETE', 'path' => 'holidays/{created:holidays}', 'body' => null]])
        ->and(array_map(fn(Request $request): string => $request->describe(), $connector->sent))->toBe(['GET subsidiaries']);
});

it('runs the steps, records each exchange, and cleans up what it created in reverse order', function () {
    $connector = writerApi();

    $recordings = iterator_to_array((new WriteRecorder($connector, '2025-01-15'))->run(calendarSteps(), execute: true));

    expect(array_map(fn(Request $request): string => $request->method->value . ' ' . $request->path, $connector->sent))->toBe([
        'GET subsidiaries', 'POST holiday_calendars', 'PATCH holiday_calendars/11', 'POST holidays', 'PATCH holiday_calendars/merge', 'DELETE holidays/22', 'DELETE holiday_calendars/11',
    ])
        ->and($connector->sent[1]->query)->toBe('')
        ->and($connector->sent[0]->query)->toBe('page[size]=1')
        ->and($connector->sent[1]->operation)->toBe('holiday_calendars.create')
        ->and($connector->sent[0]->operation)->toBe('subsidiaries.index')
        ->and(array_map(fn(Request $request): string => $request->rateLimits[0]->name, $connector->sent))->each->toBe('recorder')
        ->and($connector->sent[2]->body)->toBe(['data' => ['type' => 'holiday_calendars', 'id' => '11', 'attributes' => ['name' => 'SDK check 2']]])
        ->and($connector->sent[4]->body)->toBe(['data' => ['type' => 'holiday_calendars', 'attributes' => ['ids' => ['11']]]])
        ->and($connector->sent[5]->body)->toBeNull()
        ->and($recordings['001.holiday_calendars.create'])->toBe([
            'request' => ['method' => 'POST', 'path' => 'holiday_calendars', 'body' => ['data' => ['type' => 'holiday_calendars', 'attributes' => ['name' => 'SDK check', 'subsidiary_id' => '7']]]],
            'status' => 201,
            'headers' => ['Content-Type' => 'application/vnd.api+json'],
            'body' => ['data' => ['id' => 11, 'type' => 'holiday_calendars']],
        ])
        ->and($recordings['006.holiday_calendars.destroy']['status'])->toBe(204);
});

it('records a rejected request, skips what refers to a record that was not created, and cleans up only what was', function () {
    $connector = new WriteTestConnector(fn(Request $request): Response => match (true) {
        $request->method->value === 'GET' => writerResponse(200, ['data' => [['id' => '7', 'type' => 'subsidiaries']]]),
        $request->path === 'holiday_calendars' => writerResponse(201, ['data' => ['id' => '11', 'type' => 'holiday_calendars']]),
        $request->path === 'holidays' => writerResponse(422, ['errors' => [['detail' => "Date can't be blank"]]]),
        default => writerResponse(200, ['data' => ['id' => '99', 'type' => 'holidays']]),
    });

    $recordings = iterator_to_array((new WriteRecorder($connector, '2025-01-15'))->run(calendarSteps(), execute: true));

    expect($recordings['003.holidays.create']['status'])->toBe(422)
        ->and($recordings['003.holidays.create']['body'])->toBe(['errors' => [['detail' => "Date can't be blank"]]])
        ->and(array_keys($recordings))->toBe(['001.holiday_calendars.create', '002.holiday_calendars.update', '003.holidays.create', '004.holiday_calendars.merge', '005.holiday_calendars.destroy'])
        ->and(end($connector->sent)->path)->toBe('holiday_calendars/11');
});

it('skips a step whose reference has no record, saying which', function () {
    $empty = new WriteTestConnector(fn(Request $request): Response => writerResponse(200, ['data' => []]));
    $forbidden = new WriteTestConnector(fn(Request $request): Response => writerResponse(403, ['errors' => [['title' => 'Forbidden']]]));
    $notCreated = new WriteTestConnector(fn(Request $request): Response => writerResponse(201, ['data' => ['type' => 'holiday_calendars']]));

    expect(iterator_to_array((new WriteRecorder($empty, '2025-01-15'))->run([calendarSteps()[0]], execute: false)))->toBe(['001.holiday_calendars.create' => ['skipped' => 'There are no subsidiaries.']])
        ->and(iterator_to_array((new WriteRecorder($forbidden, '2025-01-15'))->run([calendarSteps()[0]], execute: false))['001.holiday_calendars.create']['skipped'])->toStartWith('Could not list subsidiaries: ')
        ->and(iterator_to_array((new WriteRecorder($notCreated, '2025-01-15'))->run([calendarSteps()[2]], execute: true)))->toBe(['001.holidays.create' => ['skipped' => 'This run created no holiday_calendars.']])
        ->and($empty->sent)->toHaveCount(1);
});

it('reads a list once and remembers that it is empty', function () {
    $empty = new WriteTestConnector(fn(Request $request): Response => writerResponse(200, ['data' => []]));
    $steps = [calendarSteps()[0], calendarSteps()[0]];

    iterator_to_array((new WriteRecorder($empty, '2025-01-15'))->run($steps, execute: false));

    expect($empty->sent)->toHaveCount(1);
});

it('only remembers a record a create step made and the API accepted', function () {
    $connector = new WriteTestConnector(fn(Request $request): Response => writerResponse(200, ['data' => ['id' => '5', 'type' => 'holiday_calendars']]));
    $steps = [
        writerStep('holiday_calendars.update', 'PATCH', 'holiday_calendars/1', ['name' => 'x']),
        writerStep('holiday_calendars.destroy', 'DELETE', 'holiday_calendars/{id}', null, onCreated: true, cleanup: true),
    ];

    expect(array_keys(iterator_to_array((new WriteRecorder($connector, '2025-01-15'))->run($steps, execute: true))))->toBe(['001.holiday_calendars.update']);
});

it('keeps what it created when asked to', function () {
    $connector = writerApi();

    $recordings = iterator_to_array((new WriteRecorder($connector, '2025-01-15'))->run(calendarSteps(), execute: true, keep: true));

    expect(array_keys($recordings))->toHaveCount(4)
        ->and(array_filter($connector->sent, fn(Request $request): bool => $request->method->value === 'DELETE'))->toBe([]);
});

it('cleans up after a step throws, then throws again', function () {
    $connector = new WriteTestConnector(function (Request $request): Response {
        if ($request->path === 'holidays') {
            throw new RuntimeException('Connection reset');
        }

        return writerResponse(201, ['data' => ['id' => '11', 'type' => 'holiday_calendars']]);
    });
    $steps = calendarSteps();
    $steps[0] = writerStep('holiday_calendars.create', 'POST', 'holiday_calendars', ['name' => 'SDK check'], creates: true);
    $seen = [];

    expect(function () use ($connector, $steps, &$seen): void {
        foreach ((new WriteRecorder($connector, '2025-01-15'))->run($steps, execute: true) as $name => $recording) {
            $seen[] = $name;
        }
    })->toThrow(RuntimeException::class, 'Connection reset');

    // Step 3 threw, so it was never yielded; the cleanup keeps its own number.
    expect($seen)->toBe(['001.holiday_calendars.create', '002.holiday_calendars.update', '004.holiday_calendars.destroy'])
        ->and(end($connector->sent)->describe())->toBe('DELETE holiday_calendars/11');
});

it('stops on a 429 from a write or from reading a list', function () {
    $writes = new WriteTestConnector(fn(Request $request): Response => $request->method->value === 'GET' ? writerResponse(200, ['data' => [['id' => '7']]]) : writerResponse(429));
    $reads = new WriteTestConnector(fn(Request $request): Response => writerResponse(429));

    expect(fn() => iterator_to_array((new WriteRecorder($writes, '2025-01-15'))->run(calendarSteps(), execute: true)))->toThrow(RateLimitException::class)
        ->and(fn() => iterator_to_array((new WriteRecorder($reads, '2025-01-15'))->run(calendarSteps(), execute: false)))->toThrow(RateLimitException::class);
});
