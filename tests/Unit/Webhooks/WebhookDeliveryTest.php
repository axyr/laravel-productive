<?php

declare(strict_types=1);

use Axyr\Productive\Data\GenericModel;
use Axyr\Productive\Data\Models\Company;
use Axyr\Productive\Data\Models\Deal;
use Axyr\Productive\Data\Models\Person;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Webhooks\WebhookDelivery;
use Axyr\Productive\Webhooks\WebhookEvent;

/**
 * @return array<string, mixed>
 */
function webhookFixture(string $event): array
{
    $decoded = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/Webhooks/' . $event . '.json'), true, flags: JSON_THROW_ON_ERROR);

    return is_array($decoded) ? $decoded : [];
}

it('reads a recorded delivery and hydrates its item with the included resources', function () {
    $delivery = WebhookDelivery::fromPayload(webhookFixture('create_deal'));

    expect($delivery->name)->toBe('create_deal')
        ->and($delivery->event())->toBe(WebhookEvent::DealCreated)
        ->and($delivery->itemType)->toBe('deal')
        ->and($delivery->itemId)->toBe((string) webhookFixture('create_deal')['item_id'])
        ->and($delivery->createdAt->format(DATE_RFC3339_EXTENDED))->toBe(webhookFixture('create_deal')['created'])
        ->and($delivery->model)->toBeInstanceOf(Deal::class)
        ->and($delivery->model?->id)->toBe($delivery->itemId)
        ->and($delivery->model?->company())->toBeInstanceOf(Company::class)
        ->and($delivery->model?->creator())->toBeInstanceOf(Person::class)
        ->and($delivery->payload)->toBe(webhookFixture('create_deal'));
});

it('hydrates every recorded delivery into the model of its type', function (string $event) {
    $payload = webhookFixture($event);
    $delivery = WebhookDelivery::fromPayload($payload);

    expect($delivery->event())->not->toBeNull()
        ->and($delivery->event()?->itemType())->toBe($delivery->itemType)
        ->and($delivery->model)->not->toBeInstanceOf(GenericModel::class)
        ->and($delivery->model?->type)->toBe($payload['object']['data']['type'])
        ->and($delivery->model?->id)->toBe($delivery->itemId);
})->with(array_map(fn(string $file): string => basename($file, '.json'), glob(dirname(__DIR__, 2) . '/Fixtures/Webhooks/*.json') ?: []));

it('accepts a delivery without an object, an unknown event and a string item id', function () {
    $delivery = WebhookDelivery::fromPayload(['event' => 'vaporize_task', 'item_type' => 'task', 'item_id' => '7', 'created' => 'not a date']);

    expect($delivery->model)->toBeNull()
        ->and($delivery->event())->toBeNull()
        ->and($delivery->itemId)->toBe('7')
        ->and($delivery->createdAt->getTimestamp())->toBeGreaterThan(time() - 5)
        ->and(WebhookDelivery::fromPayload(['event' => 'x_y', 'item_type' => 'y', 'item_id' => 1, 'object' => ['data' => null]])->model)->toBeNull()
        ->and(WebhookDelivery::fromPayload(['event' => 'x_y', 'item_type' => 'y', 'item_id' => 1, 'created' => '2026-10-23T13:40:22+02:00'])->createdAt->format('c'))->toBe('2026-10-23T13:40:22+02:00');
});

it('refuses a body that is not a delivery', function (array $payload) {
    expect(fn() => WebhookDelivery::fromPayload($payload))->toThrow(InvalidResponseException::class, 'not a Productive webhook delivery');
})->with([
    'empty' => [[]],
    'no item type' => [['event' => 'create_task', 'item_id' => 1]],
    'no item id' => [['event' => 'create_task', 'item_type' => 'task']],
    'wrong types' => [['event' => 1, 'item_type' => 'task', 'item_id' => 1]],
    'list item id' => [['event' => 'create_task', 'item_type' => 'task', 'item_id' => [1]]],
]);
