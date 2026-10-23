<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

/**
 * Dispatched for every verified delivery the webhook route receives. Listen to it to act on
 * the event; the delivery holds the hydrated item.
 */
final readonly class ProductiveWebhookReceived
{
    public function __construct(
        public WebhookDelivery $delivery,
    ) {}
}
