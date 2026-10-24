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
 *
 * The test Productive sends from its webhook settings is named after the event with a "test_"
 * prefix ("test_create_task") and carries no item: its item ID is null and its object holds
 * sample rows, not a resource.
 */
final readonly class WebhookDelivery
{
    private const string TEST_PREFIX = 'test_';

    /**
     * @param  string  $name  The event name the delivery carries, e.g. "create_company" or "test_create_company".
     * @param  string  $itemType  The item type the delivery names, e.g. "company".
     * @param  string|null  $itemId  The item's ID; null for a test.
     * @param  Model|null  $model  The item, hydrated with its included resources; null when the delivery carries no object (a deletion) and for a test.
     * @param  array<string, mixed>  $payload  The whole delivery as decoded.
     * @param  bool  $test  Whether this is the test Productive sends from its webhook settings.
     */
    public function __construct(
        public string $name,
        public string $itemType,
        public ?string $itemId,
        public DateTimeImmutable $createdAt,
        public ?Model $model,
        public array $payload,
        public bool $test = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload  The decoded request body.
     *
     * @throws InvalidResponseException When the body is not a delivery.
     */
    public static function fromPayload(array $payload, ModelRegistry $registry = new ModelRegistry()): self
    {
        [$name, $itemType, $itemId] = self::identity($payload);
        $test = str_starts_with($name, self::TEST_PREFIX);
        $delivery = [];

        foreach ($payload as $key => $value) {
            // PHP stores a numeric key as int either way: the cast only tells static analysis.
            $delivery[(string) $key] = $value; // @pest-mutate-ignore
        }

        return new self($name, $itemType, $itemId, self::createdAt($payload['created'] ?? null), $test ? null : self::model($payload['object'] ?? null, $registry), $delivery, $test);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{string, string, string|null}  The event name, item type and item ID.
     */
    private static function identity(array $payload): array
    {
        $name = $payload['event'] ?? null;
        $itemType = $payload['item_type'] ?? null;
        $itemId = $payload['item_id'] ?? null;

        if (! is_string($name) || ! is_string($itemType) || ! self::isItemId($itemId, $name)) {
            throw new InvalidResponseException('The request is not a Productive webhook delivery: "event", "item_type" and "item_id" are expected.');
        }

        return [$name, $itemType, self::itemId($itemId)];
    }

    /**
     * An ID, or nothing for a test.
     */
    private static function isItemId(mixed $id, string $name): bool
    {
        return is_string($id) || is_int($id) || ($id === null && str_starts_with($name, self::TEST_PREFIX));
    }

    private static function itemId(mixed $id): ?string
    {
        return is_string($id) || is_int($id) ? (string) $id : null;
    }

    /**
     * The event, when this version knows its name; for a test, the event it tests.
     */
    public function event(): ?WebhookEvent
    {
        return WebhookEvent::fromDeliveryName($this->test ? substr($this->name, strlen(self::TEST_PREFIX)) : $this->name);
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
