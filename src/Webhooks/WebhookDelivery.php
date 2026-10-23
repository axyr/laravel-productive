<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

use Axyr\Productive\Data\Model;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\JsonApi\Document;
use DateTimeImmutable;

/**
 * One webhook delivery as Productive sends it: `{"event": "create_company", "item_type":
 * "company", "item_id": 1579076, "created": "...", "object": {"data": {...}, "included": [...]}}`.
 * The object is a JSON:API document of the item with its related resources, hydrated like an
 * API response.
 */
final readonly class WebhookDelivery
{
    /**
     * @param  string  $name  The event name the delivery carries, e.g. "create_company".
     * @param  string  $itemType  The item type the delivery names, e.g. "company".
     * @param  string  $itemId  The item's ID.
     * @param  Model|null  $model  The item, hydrated with its included resources; null when the delivery carries no object (a deletion).
     * @param  array<string, mixed>  $payload  The whole delivery as decoded.
     */
    public function __construct(
        public string $name,
        public string $itemType,
        public string $itemId,
        public DateTimeImmutable $createdAt,
        public ?Model $model,
        public array $payload,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload  The decoded request body.
     *
     * @throws InvalidResponseException When the body is not a delivery.
     */
    public static function fromPayload(array $payload, ModelRegistry $registry = new ModelRegistry()): self
    {
        [$name, $itemType, $itemId] = self::identity($payload);
        $delivery = [];

        foreach ($payload as $key => $value) {
            $delivery[(string) $key] = $value;
        }

        return new self($name, $itemType, $itemId, self::createdAt($payload['created'] ?? null), self::model($payload['object'] ?? null, $registry), $delivery);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{string, string, string}  The event name, item type and item ID.
     */
    private static function identity(array $payload): array
    {
        $name = $payload['event'] ?? null;
        $itemType = $payload['item_type'] ?? null;
        $itemId = $payload['item_id'] ?? null;

        if (! is_string($name) || ! is_string($itemType) || ! self::isId($itemId)) {
            throw new InvalidResponseException('The request is not a Productive webhook delivery: "event", "item_type" and "item_id" are expected.');
        }

        return [$name, $itemType, (string) $itemId];
    }

    /**
     * @phpstan-assert-if-true string|int $id
     */
    private static function isId(mixed $id): bool
    {
        return is_string($id) || is_int($id);
    }

    /**
     * The event, when this version knows its name.
     */
    public function event(): ?WebhookEvent
    {
        return WebhookEvent::fromDeliveryName($this->name);
    }

    private static function createdAt(mixed $created): DateTimeImmutable
    {
        $parsed = is_string($created) ? DateTimeImmutable::createFromFormat(DATE_RFC3339_EXTENDED, $created) ?: DateTimeImmutable::createFromFormat(DATE_ATOM, $created) : false;

        return $parsed === false ? new DateTimeImmutable() : $parsed;
    }

    private static function model(mixed $object, ModelRegistry $registry): ?Model
    {
        if (! is_array($object) || ! is_array($object['data'] ?? null)) {
            return null;
        }

        $document = Document::fromArray($object);

        return $registry->hydrate($document->resource(), $document->index());
    }
}
