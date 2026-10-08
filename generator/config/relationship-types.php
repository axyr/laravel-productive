<?php

declare(strict_types=1);

/*
 * JSON:API types of relationships the spec's examples do not show and whose names do not
 * pluralize into a known type. Keys are "relationship" or "owner_type.relationship";
 * null marks a polymorphic relationship, which is exposed as a generic Model.
 *
 * A wrong entry is not silent: a typed accessor that finds another type throws.
 */
return [
    'actual_approver' => 'people',
    'approver' => 'people',
    'assignee' => 'people',
    'bill_from' => 'contact_entries',
    'bill_to' => 'contact_entries',
    'budget' => 'deals',
    'canceler' => 'people',
    'creator' => 'people',
    'deleter' => 'people',
    'designated_approver' => 'people',
    'last_actor' => 'people',
    'manager' => 'people',
    'owner' => 'people',
    'pinned_by' => 'people',
    'rejecter' => 'people',
    'resolver' => 'people',
    'responsible' => 'people',
    'subscribers' => 'people',
    'template_object' => null,
    'updater' => 'people',
];
