<?php

declare(strict_types=1);

use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\FakeResponse;

it('matches method and path patterns case-insensitively on the method', function () {
    $connector = new FakeConnector(['get search/with space' => FakeResponse::noContent()]);
    $connector->preventStrayRequests();

    expect($connector->send(new Request(Method::Get, 'search/with space'))->status)->toBe(204);
});

it('echoes the submitted type and id over the path', function () {
    $connector = new FakeConnector();
    $response = $connector->send(new Request(Method::Patch, 'reports/7', body: ['data' => ['type' => 'custom', 'id' => '9', 'attributes' => ['a' => 1]]]));

    expect($response->status)->toBe(200)
        ->and($response->json()['data'])->toBe(['type' => 'custom', 'id' => '9', 'attributes' => ['a' => 1]]);
});

it('answers creates with 201 and derives type and id from the path otherwise', function () {
    $connector = new FakeConnector();

    $created = $connector->send(new Request(Method::Post, 'tasks', body: ['data' => ['type' => 'tasks']]));
    $action = $connector->send(new Request(Method::Patch, 'time_entries/55/approve'));
    $noId = $connector->send(new Request(Method::Patch, 'tasks'));

    expect($created->status)->toBe(201)
        ->and($created->json()['data']['id'])->toBe('1')
        ->and($action->status)->toBe(200)
        ->and($action->json()['data'])->toBe(['type' => 'time_entries', 'id' => '55', 'attributes' => []])
        ->and($noId->json()['data']['id'])->toBe('2');
});

it('generates an id when the path segment is an action rather than an id', function () {
    $response = (new FakeConnector())->send(new Request(Method::Post, 'tasks/copy', body: ['data' => ['type' => 'tasks']]));

    expect($response->json()['data']['id'])->toBe('1');
});
