<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

/**
 * The events a webhook can fire after: the `event_id` of a webhook, with the labels of
 * Productive's API reference and the names its deliveries carry ("create_company").
 */
enum WebhookEvent: int
{
    case TaskCreated = 1;
    case InvoiceCreated = 2;
    case DealCreated = 3;
    case BudgetCreated = 4;
    case ProjectCreated = 5;
    case ContactCreated = 6;
    case TimeEntryCreated = 7;
    case TimeEntryUpdated = 8;
    case TimeEntryDeleted = 9;
    case ProjectUpdated = 10;
    case ProjectDeleted = 11;
    case DealUpdated = 12;
    case DealDeleted = 13;
    case InvoiceUpdated = 14;
    case InvoiceDeleted = 15;
    case BudgetUpdated = 16;
    case BookingCreated = 17;
    case BookingUpdated = 18;
    case BookingDeleted = 19;
    case BudgetDeleted = 20;
    case ExpenseCreated = 21;
    case ExpenseUpdated = 22;
    case ExpenseDeleted = 23;
    case TaskUpdated = 24;
    case TaskDeleted = 25;
    case PersonCreated = 26;
    case PersonUpdated = 27;
    case PersonArchived = 28;
    case CompanyCreated = 29;
    case CompanyUpdated = 30;
    case CompanyArchived = 31;
    case PaymentCreated = 32;
    case PaymentUpdated = 33;
    case PaymentDeleted = 34;
    case ContactUpdated = 35;

    /**
     * The event by the name a delivery carries, e.g. "create_company"; null for a name this
     * version does not know.
     */
    public static function fromDeliveryName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->deliveryName() === $name) {
                return $case;
            }
        }

        return null;
    }

    /**
     * The name a delivery carries: the action, then the item type ("create_time_entry").
     */
    public function deliveryName(): string
    {
        return $this->action() . '_' . $this->itemType();
    }

    /**
     * "create", "update", "delete" or "archive".
     */
    public function action(): string
    {
        return match (substr($this->name, -7)) {
            'Created' => 'create',
            'Updated' => 'update',
            'Deleted' => 'delete',
            default => 'archive',
        };
    }

    /**
     * The item type a delivery names, in snake case: "time_entry".
     */
    public function itemType(): string
    {
        $subject = (string) preg_replace('/(Created|Updated|Deleted|Archived)$/', '', $this->name);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $subject));
    }

    /**
     * The label of Productive's API reference, e.g. "New task".
     */
    public function label(): string
    {
        $verb = match ($this->action()) {
            'create' => 'New',
            'update' => 'Updated',
            'delete' => 'Deleted',
            default => 'Archived',
        };

        return $verb . ' ' . str_replace('_', ' ', $this->itemType());
    }
}
