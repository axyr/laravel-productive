<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Testing\Factories\TaskFactory;
use Axyr\Productive\Testing\FakeWebhook;
use Axyr\Productive\Webhooks\WebhookEvent;
use Axyr\Productive\Webhooks\WebhookSignature;

it('builds a payload like a delivery from a resource or a model', function () {
    $created = new DateTimeImmutable('2026-10-23T13:40:22.622+02:00');
    $resource = TaskFactory::new()->resource(['id' => '9', 'title' => 'Write docs']);
    $included = [['id' => '2', 'type' => 'people', 'attributes' => []]];

    $payload = FakeWebhook::payload(WebhookEvent::TaskCreated, $resource, $included, $created);
    $fromModel = FakeWebhook::payload('update_task', TaskFactory::new()->make(['title' => 'Model']), created: $created);

    expect($payload)->toBe(['event' => 'create_task', 'item_type' => 'task', 'item_id' => 9, 'created' => '2026-10-23T13:40:22.622+02:00', 'object' => ['data' => $resource, 'included' => $included]])
        ->and($fromModel['event'])->toBe('update_task')
        ->and($fromModel['item_type'])->toBe('task')
        ->and($fromModel['object']['data']['attributes']['title'])->toBe('Model')
        ->and($fromModel['item_id'])->toBe((int) $fromModel['object']['data']['id'])
        ->and(FakeWebhook::payload('weird', ['type' => 'tasks', 'id' => 'abc'])['item_id'])->toBe('abc')
        ->and(FakeWebhook::payload('weird', ['type' => 'tasks', 'id' => 'abc'])['item_type'])->toBe('weird')
        ->and(FakeWebhook::payload('create_task', ['type' => 'tasks'])['item_id'])->toBeNull()
        ->and(FakeWebhook::payload('create_task', ['type' => 'tasks'])['created'])->not->toBe('');
});

it('signs a payload as Productive would, and builds the event the route dispatches', function () {
    $payload = FakeWebhook::payload(WebhookEvent::TaskCreated, TaskFactory::new()->resource(['id' => '9']));

    $request = FakeWebhook::request($payload, 'tok', 1700000000);
    $received = FakeWebhook::received($payload);

    expect($request['headers'])->toBe(['Productive-Signature' => WebhookSignature::sign($request['body'], 'tok', 1700000000), 'Content-Type' => 'application/json'])
        ->and(json_decode($request['body'], true))->toBe($payload)
        ->and((new WebhookSignature(['tok']))->verify($request['headers']['Productive-Signature'], $request['body'], 1700000000))->toBeTrue()
        ->and($received->delivery->model)->toBeInstanceOf(Task::class)
        ->and($received->delivery->itemId)->toBe('9');
});
