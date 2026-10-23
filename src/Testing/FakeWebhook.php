<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Webhooks\ProductiveWebhookReceived;
use Axyr\Productive\Webhooks\WebhookDelivery;
use Axyr\Productive\Webhooks\WebhookEvent;
use Axyr\Productive\Webhooks\WebhookSignature;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Builds webhook deliveries the way Productive sends them, for tests: the payload, its signed
 * request, or the dispatched event.
 */
final class FakeWebhook
{
    /**
     * The payload of a delivery for a resource, as Productive sends it.
     *
     * @param  Model|array<string, mixed>  $resource  A JSON:API resource object such as a factory's `resource()`, or a model (its attributes only).
     * @param  list<array<string, mixed>>  $included  Related resource objects.
     * @return array<string, mixed>
     */
    public static function payload(WebhookEvent|string $event, Model|array $resource, array $included = [], ?DateTimeInterface $created = null): array
    {
        $data = $resource instanceof Model ? $resource->toArray() : $resource;
        [$name, $itemType] = self::names($event);

        return [
            'event' => $name,
            'item_type' => $itemType,
            'item_id' => self::itemId($data['id'] ?? null),
            'created' => ($created ?? new DateTimeImmutable())->format(DATE_RFC3339_EXTENDED),
            'object' => ['data' => $data, 'included' => $included],
        ];
    }

    /**
     * @return array{string, string}  The delivery name and the item type: "create_company" names a "company".
     */
    private static function names(WebhookEvent|string $event): array
    {
        if ($event instanceof WebhookEvent) {
            return [$event->deliveryName(), $event->itemType()];
        }

        return [$event, explode('_', $event, 2)[1] ?? $event];
    }

    /**
     * Productive sends the item ID as a number.
     */
    private static function itemId(mixed $id): mixed
    {
        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * The body and headers of a signed request for a payload, for `$this->call('POST', $uri, server: ..., content: ...)`:
     * `['body' => '{...}', 'headers' => ['Productive-Signature' => 't=...,s=...', 'Content-Type' => 'application/json']]`.
     *
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public static function request(array $payload, string $secret, ?int $timestamp = null): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['body' => $body, 'headers' => [WebhookSignature::HEADER => WebhookSignature::sign($body, $secret, $timestamp), 'Content-Type' => 'application/json']];
    }

    /**
     * The event the route would dispatch for a payload, to test a listener directly.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function received(array $payload, ModelRegistry $registry = new ModelRegistry()): ProductiveWebhookReceived
    {
        return new ProductiveWebhookReceived(WebhookDelivery::fromPayload($payload, $registry));
    }
}
