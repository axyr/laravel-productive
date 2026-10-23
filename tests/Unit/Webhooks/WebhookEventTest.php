<?php

declare(strict_types=1);

use Axyr\Productive\Webhooks\WebhookEvent;

it('has the 35 events of the API reference with their ids', function () {
    expect(count(WebhookEvent::cases()))->toBe(35)
        ->and(WebhookEvent::from(1))->toBe(WebhookEvent::TaskCreated)
        ->and(WebhookEvent::from(35))->toBe(WebhookEvent::ContactUpdated)
        ->and(WebhookEvent::from(28))->toBe(WebhookEvent::PersonArchived);
});

it('names deliveries the way Productive does, as recorded', function (WebhookEvent $event, string $name, string $itemType, string $action) {
    expect($event->deliveryName())->toBe($name)
        ->and($event->itemType())->toBe($itemType)
        ->and($event->action())->toBe($action)
        ->and(WebhookEvent::fromDeliveryName($name))->toBe($event);
})->with([
    'company created' => [WebhookEvent::CompanyCreated, 'create_company', 'company', 'create'],
    'deal created' => [WebhookEvent::DealCreated, 'create_deal', 'deal', 'create'],
    'deal updated' => [WebhookEvent::DealUpdated, 'update_deal', 'deal', 'update'],
    'person created' => [WebhookEvent::PersonCreated, 'create_person', 'person', 'create'],
    'time entry deleted' => [WebhookEvent::TimeEntryDeleted, 'delete_time_entry', 'time_entry', 'delete'],
    'company archived' => [WebhookEvent::CompanyArchived, 'archive_company', 'company', 'archive'],
]);

it('labels events like the API reference', function () {
    expect(WebhookEvent::TaskCreated->label())->toBe('New task')
        ->and(WebhookEvent::TimeEntryUpdated->label())->toBe('Updated time entry')
        ->and(WebhookEvent::BookingDeleted->label())->toBe('Deleted booking')
        ->and(WebhookEvent::PersonArchived->label())->toBe('Archived person');
});

it('knows no delivery name twice and none it did not define', function () {
    $names = array_map(fn(WebhookEvent $event): string => $event->deliveryName(), WebhookEvent::cases());

    expect(array_unique($names))->toHaveCount(35)
        ->and(WebhookEvent::fromDeliveryName('explode_everything'))->toBeNull();
});
