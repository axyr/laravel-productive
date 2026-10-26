<?php

declare(strict_types=1);

/*
 * Why an operation of docs/coverage.md cannot be recorded or exercised, by resource path or by
 * operation key. An operation the recording did answer with a success is shown as recorded
 * whatever is listed here.
 */
return [
    'skipped' => [
        'sessions' => 'Authentication: never called by the recorder.',
        'passwords' => 'Authentication: never called by the recorder.',
        'public/artifacts' => 'Reachable by a uuid the recorder cannot know.',
        'public/pages' => 'Reachable by the uuid of a publicly shared page: set `RECORDING_PUBLIC_PAGE_UUID` in `.env` to record it.',
        'integrations.check' => 'Runs the integration check.',
        'integrations.sync' => 'Starts a sync.',
        'deal_statuses.merge' => 'The body is undocumented, and `winner` and `loser` in its attributes answered 404 for two statuses the run created.',
        'service_types.merge' => 'The body is undocumented: an empty body answers 422 "can\'t be blank" for `winner` and `loser`, and so do those attributes as IDs, integers, objects and relationships.',
        'people.merge' => 'The body is undocumented: an empty body answers 422 "can\'t be blank" for `winner` and `loser`, and so do those attributes as IDs and integers, as `winner_id` and `loser_id`, as relationships, as plain JSON and as query parameters.',
        'people.invite' => 'Sends an invitation email.',
        'people.resend' => 'Sends an invitation email again.',
        'people.activate' => 'Makes a person a user, which takes a seat of the subscription.',
        'people.deactivate' => 'Frees a user\'s seat; the test organization has no user to spare.',
        'people.virtualize' => 'Only a user can be virtualized (a contact answers 409 `person_not_user`), and that changes the account.',
        'people.regenerate_recovery_codes' => 'Replaces a user\'s two-factor recovery codes.',
        'expense_line_items.create' => 'The route does not exist (404 `route_not_found`): line items of an expense are made in bulk.',
        'expenses.unreject' => 'Only a rejected expense is unrejected (409 `record_already_unrejected`), and without an expense approval policy, which only the UI assigns, rejecting answers 200 and leaves the expense as it was.',
        'line_items.generate' => 'The body is undocumented: `invoice_id` and `tax_rate_id` answer 422 "can\'t be blank" for `invoice` and `tax_rate` as attributes, as strings, as relationships, as query parameters and under another resource type, and plain JSON answers 400.',
        'proposals.signed_pdf' => 'Only a signed proposal has one (an unsigned one answers 404), and only the client signs.',
    ],
    'plan_gated' => [],
];
