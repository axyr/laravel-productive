[Back to documentation](README.md)

# Webhooks

Productive can call your application when something changes: a task is created, a deal updated, a person archived. The SDK receives those deliveries, verifies their signature, hydrates the item into its model and dispatches a Laravel event.

Webhooks are an Ultimate-plan feature. Create them under Settings → Webhooks in Productive, one per event, with your route as the target URL; or through the SDK with `Productive::webhooks()->create()`. Each webhook has its own signature token, shown in Productive's webhook logs.

## Receiving deliveries

Register the route, in `routes/api.php` so no CSRF check applies:

```php
use Illuminate\Support\Facades\Route;

Route::productiveWebhooks();                    // POST /productive/webhooks, named productive.webhooks
Route::productiveWebhooks('hooks/productive');  // another URI
```

Put the signature tokens of your webhooks in `.env`, comma separated:

```dotenv
PRODUCTIVE_WEBHOOK_SECRETS=token-of-the-task-webhook,token-of-the-deal-webhook
```

Then listen to `ProductiveWebhookReceived`:

```php
use Axyr\Productive\Data\Models\Deal;
use Axyr\Productive\Webhooks\ProductiveWebhookReceived;
use Axyr\Productive\Webhooks\WebhookEvent;

final class SyncDeal
{
    public function handle(ProductiveWebhookReceived $event): void
    {
        $delivery = $event->delivery;

        if ($delivery->event() !== WebhookEvent::DealUpdated || ! $delivery->model instanceof Deal) {
            return;
        }

        $deal = $delivery->model;              // hydrated with the included resources
        $company = $deal->company();           // a Company, included in the delivery
        // ...
    }
}
```

The route answers every verified delivery with 200 right away, so do the work in a queued listener: Productive retries a delivery that does not get a 2xx within its timeout, twelve times over two days, and deactivates the webhook after that.

## The delivery

`WebhookDelivery` holds what Productive sent:

| Property | |
|---|---|
| `name` | The event as Productive names it: `create_company`, `update_deal`, `archive_person`; `test_create_company` for a test. |
| `event()` | The `WebhookEvent` case for that name (for a test, the event it tests), or null for a name this version does not know. |
| `itemType` | The item as Productive names it: `company`, `deal`, `time_entry`. |
| `itemId` | The item's ID; null for a test. |
| `createdAt` | When the event happened. |
| `model` | The item, hydrated like an API response, with the resources Productive included (its company, creator, status, …). Null when the delivery carries no object, and for a test. |
| `payload` | The whole decoded body. |
| `test` | Whether this is the test Productive sends from its webhook settings. |

The body Productive sends looks like this, with `object` a JSON:API document of the item:

```json
{
  "event": "create_company",
  "item_type": "company",
  "item_id": 1579076,
  "created": "2026-10-23T13:40:22.622+02:00",
  "object": {"data": {"id": "1579076", "type": "companies", "attributes": {...}, "relationships": {...}}, "included": [...]}
}
```

`WebhookEvent` lists the 35 events with their `event_id` (the attribute a webhook is created with), their label from the API reference and their delivery name:

```php
WebhookEvent::TaskCreated->value;           // 1
WebhookEvent::TaskCreated->label();         // "New task"
WebhookEvent::TaskCreated->deliveryName();  // "create_task"
WebhookEvent::fromDeliveryName('update_deal'); // WebhookEvent::DealUpdated
```

The delivery names of company, deal, person and task events were recorded from real deliveries, and the test deliveries confirm the names of the time entry, invoice, payment, expense, booking, budget, project and contact create events; the others follow the same pattern (`<create|update|delete|archive>_<item type>`) and are confirmed as deliveries are captured.

### Test deliveries

The "Test webhook" action in Productive's webhook settings sends the webhook's event with a `test_` prefix and no item:

```json
{"event": "test_create_task", "item_type": "task", "item_id": null, "created": "…", "object": {"data": {"id": null, "type": "tasks", "attributes": [{"Title": "…", "Project": "…"}, …]}}}
```

Its `attributes` are sample rows, not a resource, so `model` stays null. The route answers it with 200 and dispatches `ProductiveWebhookReceived` like any delivery, with `$delivery->test` true; skip it in a listener that only acts on real items. Its `item_type` does not always match the real one: a time entry test names `time-entry`, a contact test `person`.

## Verification

Productive signs every delivery: the `Productive-Signature` header holds `t=<unix seconds>,s=<hex>`, where `s` is HMAC-SHA256 over `<t>.<raw request body>` with the webhook's signature token. The `VerifyProductiveSignature` middleware, which the route carries, checks the signature against every configured token and rejects a delivery with 401 when none matches or when the timestamp is more than `PRODUCTIVE_WEBHOOK_TOLERANCE` seconds (300 by default) from now. Without a configured token the route throws a `WebhookException` rather than accept anything.

A body that verifies but is not a delivery is answered with 400.

## Testing your listener

See [Testing](testing.md#webhooks): `FakeWebhook` builds payloads, signed requests and the dispatched event.

---

Previous: [Errors, retries and rate limits](errors.md) · Next: [Testing](testing.md)
