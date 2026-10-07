<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\JsonApi\Document;
use Axyr\Productive\Testing\Factories\TaskFactory;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\FakeResponse;
use Axyr\Productive\Testing\ResponseSequence;

it('builds a JSON:API document response', function () {
    $response = FakeResponse::json(['data' => null], 201, ['X-Extra' => 'y']);

    expect($response->status)->toBe(201)
        ->and($response->header('Content-Type'))->toBe('application/vnd.api+json')
        ->and($response->header('X-Extra'))->toBe('y')
        ->and($response->json())->toBe(['data' => null]);
});

it('serializes models with their relationships', function () {
    $task = TaskFactory::new()
        ->relatedTo('assignee', 'people', '12')
        ->relatedTo('attachments', 'attachments', ['1', '2'])
        ->relatedTo('service', 'services', null)
        ->make(['id' => '5', 'title' => 'T']);
    $document = Document::fromArray(FakeResponse::resource($task)->json());
    $resource = $document->resource();

    expect($resource->id)->toBe('5')
        ->and($resource->attributes['title'])->toBe('T')
        ->and($resource->relationship('assignee')?->identifiers()[0]->id)->toBe('12')
        ->and($resource->relationship('attachments')?->identifiers())->toHaveCount(2)
        ->and($resource->relationship('service')?->hasData)->toBeTrue()
        ->and($resource->relationship('service')?->data)->toBeNull();
});

it('keeps relationships that were not included as meta', function () {
    $model = new Task(Document::fromArray(['data' => ['type' => 'tasks', 'id' => '1', 'relationships' => ['project' => ['meta' => ['included' => false]]]]])->resource());

    expect(FakeResponse::resource($model)->json()['data']['relationships'])->toBe(['project' => ['meta' => ['included' => false]]]);
});

it('leaves out relationships when a model has none', function () {
    expect(FakeResponse::resource(TaskFactory::new()->make(['id' => '1']))->json()['data'])->not->toHaveKey('relationships');
});

it('builds collections with pagination meta', function () {
    $response = FakeResponse::collection(TaskFactory::new()->count(3)->makeMany(), [TaskFactory::new()->resource()], ['current_page' => 2], ['next' => 'x']);
    $json = $response->json();

    expect($json['data'])->toHaveCount(3)
        ->and($json['included'])->toHaveCount(1)
        ->and($json['meta'])->toBe(['current_page' => 2, 'total_pages' => 1, 'total_count' => 3, 'page_size' => 30, 'max_page_size' => 200])
        ->and($json['links'])->toBe(['next' => 'x'])
        ->and(FakeResponse::collection()->json()['meta']['total_pages'])->toBe(0)
        ->and(FakeResponse::collection(TaskFactory::new()->count(40)->resources())->json()['meta']['page_size'])->toBe(40);
});

it('builds no content, binary and error responses', function () {
    expect(FakeResponse::noContent()->status)->toBe(204)
        ->and(FakeResponse::binary('%PDF')->body)->toBe('%PDF')
        ->and(FakeResponse::binary('%PDF')->header('content-type'))->toBe('application/pdf')
        ->and(FakeResponse::error(404, 'Record Not Found')->json())->toBe(['errors' => [['status' => 404, 'title' => 'Record Not Found']]])
        ->and(FakeResponse::validation(['title' => 'x'])->json()['errors'][0]['source'])->toBe(['pointer' => '/data/attributes/title'])
        ->and(FakeResponse::rateLimited()->header('X-RateLimit-Reset'))->toBe('10');
});

it('answers binary requests with an empty body by default', function () {
    $response = (new FakeConnector())->send(new Request(Method::Get, 'proposals/1/signed_pdf', expect: Expect::Binary));

    expect($response->body)->toBe('');
});

it('knows when a sequence is empty', function () {
    $sequence = FakeResponse::sequence(FakeResponse::noContent());

    expect($sequence)->toBeInstanceOf(ResponseSequence::class)
        ->and($sequence->isEmpty())->toBeFalse();

    $sequence->next(new Request(Method::Get, 'x'));

    expect($sequence->isEmpty())->toBeTrue();
});

it('builds exact documents', function () {
    expect(FakeResponse::resource(['type' => 'tasks', 'id' => '1'])->json())->toBe(['data' => ['type' => 'tasks', 'id' => '1'], 'included' => []])
        ->and(FakeResponse::collection([['type' => 'tasks', 'id' => '1']])->json()['meta'])->toBe(['current_page' => 1, 'total_pages' => 1, 'total_count' => 1, 'page_size' => 30, 'max_page_size' => 200])
        ->and(FakeResponse::binary('x')->status)->toBe(200)
        ->and(FakeResponse::error(404, 'Record Not Found', 'Gone', 'nf')->json())->toBe(['errors' => [['status' => 404, 'title' => 'Record Not Found', 'detail' => 'Gone', 'code' => 'nf']]])
        ->and(FakeResponse::validation(['title' => 'blank'])->json())->toBe(['errors' => [['status' => 422, 'title' => 'Invalid Attribute', 'detail' => 'blank', 'source' => ['pointer' => '/data/attributes/title']]]])
        ->and(FakeResponse::rateLimited(5)->json())->toBe(['errors' => [['status' => 429, 'title' => 'Too many requests', 'detail' => 'Rate limit exceeded', 'limit' => 100, 'period' => 10]]])
        ->and(FakeResponse::rateLimited(5)->status)->toBe(429);
});

it('serializes model relationships inside collections', function () {
    $task = TaskFactory::new()->relatedTo('assignee', 'people', '12')->make(['id' => '1']);

    expect(FakeResponse::collection([$task])->json()['data'][0]['relationships'])->toBe(['assignee' => ['data' => ['type' => 'people', 'id' => '12']]]);
});
