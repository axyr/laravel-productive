<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Company;
use Axyr\Productive\Exceptions\WebhookException;
use Axyr\Productive\Testing\Factories\CompanyFactory;
use Axyr\Productive\Testing\FakeWebhook;
use Axyr\Productive\Webhooks\ProductiveWebhookReceived;
use Axyr\Productive\Webhooks\WebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

function deliver(array $payload, string $secret = 'tok', string $uri = '/productive/webhooks', ?int $timestamp = null): TestResponse
{
    $request = FakeWebhook::request($payload, $secret, $timestamp);

    return test()->call('POST', $uri, server: ['HTTP_PRODUCTIVE_SIGNATURE' => $request['headers']['Productive-Signature'], 'CONTENT_TYPE' => 'application/json'], content: $request['body']);
}

beforeEach(function (): void {
    config()->set('productive.webhooks.secrets', 'first, tok');
    Route::productiveWebhooks();
});

it('registers a named route that dispatches the delivery as an event', function () {
    Event::fake([ProductiveWebhookReceived::class]);
    $payload = FakeWebhook::payload(WebhookEvent::CompanyCreated, CompanyFactory::new()->resource(['id' => '42', 'name' => 'Acme']));

    deliver($payload)->assertOk()->assertExactJson(['received' => true]);

    expect(route('productive.webhooks'))->toEndWith('/productive/webhooks');
    Event::assertDispatched(ProductiveWebhookReceived::class, fn(ProductiveWebhookReceived $event): bool => $event->delivery->event() === WebhookEvent::CompanyCreated
        && $event->delivery->itemId === '42'
        && $event->delivery->model instanceof Company
        && $event->delivery->model->name === 'Acme');
});

it('takes another uri', function () {
    Route::productiveWebhooks('hooks/productive');

    deliver(FakeWebhook::payload('create_task', ['id' => '1', 'type' => 'tasks']), uri: '/hooks/productive')->assertOk();
});

it('rejects a wrong, stale or missing signature with 401 and no event', function () {
    Event::fake([ProductiveWebhookReceived::class]);
    $payload = FakeWebhook::payload('create_task', ['id' => '1', 'type' => 'tasks']);

    deliver($payload, secret: 'wrong')->assertUnauthorized()->assertExactJson(['message' => 'Invalid signature.']);
    deliver($payload, timestamp: time() - 301)->assertUnauthorized();
    $this->postJson('/productive/webhooks', $payload)->assertUnauthorized();

    Event::assertNotDispatched(ProductiveWebhookReceived::class);
});

it('honours the configured tolerance, 300 seconds unless it is a number', function () {
    config()->set('productive.webhooks.tolerance', '10');
    $payload = FakeWebhook::payload('create_task', ['id' => '1', 'type' => 'tasks']);

    deliver($payload, timestamp: time() - 11)->assertUnauthorized();
    deliver($payload, timestamp: time() - 9)->assertOk();

    config()->set('productive.webhooks.tolerance', 'soon');

    deliver($payload, timestamp: time() - 301)->assertUnauthorized();
    deliver($payload, timestamp: time() - 300)->assertOk();
});

it('fails loudly without a configured signature token', function (mixed $secrets) {
    config()->set('productive.webhooks.secrets', $secrets);
    $this->withoutExceptionHandling();

    expect(fn() => deliver(FakeWebhook::payload('create_task', ['id' => '1', 'type' => 'tasks'])))->toThrow(WebhookException::class, 'PRODUCTIVE_WEBHOOK_SECRETS');
})->with(['blank' => [' , '], 'unset' => [null], 'not a string' => [['tok']]]);

it('answers 400 to a signed body that is not a delivery', function () {
    Event::fake([ProductiveWebhookReceived::class]);

    deliver(['hello' => 'world'])->assertBadRequest()->assertJsonFragment(['message' => 'The request is not a Productive webhook delivery: "event", "item_type" and "item_id" are expected.']);
    $this->call('POST', '/productive/webhooks', server: ['HTTP_PRODUCTIVE_SIGNATURE' => \Axyr\Productive\Webhooks\WebhookSignature::sign('not json', 'tok'), 'CONTENT_TYPE' => 'application/json'], content: 'not json')->assertBadRequest();

    Event::assertNotDispatched(ProductiveWebhookReceived::class);
});
