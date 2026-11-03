<?php

declare(strict_types=1);

/*
 * What docs/spec-report.md says that no recording can express, by section: per finding the
 * endpoint, what the spec says, what the API does and how to reproduce it. Write runs refer to
 * `composer record:writes` against a trial organization; nothing here names the organization,
 * its people or its records.
 */
return [
    'written' => '2026-10-25 to 2026-11-02',
    'findings' => [
        'Routes the spec documents that the API does not have' => [
            [
                'endpoint' => '`POST /expense_line_items`',
                'spec' => 'Documents the route with a bulk body only (`data` is a list), so a client reads one line item as a list of one.',
                'api' => 'A single resource document answers 404 `route_not_found`; the bulk document of one is accepted (201).',
                'reproduce' => 'POST `{"data": {"type": "expense_line_items", "attributes": {…}}}`: 404. The same item with `Content-Type: application/vnd.api+json; ext=bulk` and `data` as a list: 201.',
            ],
            [
                'endpoint' => '`GET /agent_roles`, `GET /agent_roles/{id}`, `PATCH /agent_roles/{id}`',
                'spec' => 'Documents the list, one record and an update.',
                'api' => '404 `route_not_found` for the list.',
                'reproduce' => 'GET `/api/v2/agent_roles`: 404 "Route Not Found".',
            ],
        ],
        'Bodies the spec leaves out, which no attempt found' => [
            [
                'endpoint' => '`POST /line_items/generate`',
                'spec' => 'No request body.',
                'api' => '422 "can\'t be blank" for `invoice` and `tax_rate`, whatever is sent.',
                'reproduce' => 'POST with `invoice_id` and `tax_rate_id` as attributes, as strings, as relationships, as query parameters and under another resource type: all 422 for `invoice` and `tax_rate`. Plain JSON: 400 "Missing \'data\' in request payload".',
            ],
            [
                'endpoint' => '`PATCH /people/merge`, `PATCH /service_types/merge`',
                'spec' => 'No request body.',
                'api' => '422 "can\'t be blank" for `winner` and `loser`.',
                'reproduce' => 'PATCH with `winner` and `loser` (or `winner_id` and `loser_id`) as IDs, integers, objects, relationships, plain JSON and query parameters: all 422 for `winner` and `loser`.',
            ],
            [
                'endpoint' => '`PATCH /deal_statuses/merge`',
                'spec' => 'No request body.',
                'api' => '404 with `winner` and `loser` in the attributes.',
                'reproduce' => 'PATCH with the IDs of two deal statuses of the organization as `winner` and `loser`: 404.',
            ],
        ],
        'Behaviour the spec does not describe' => [
            [
                'endpoint' => '`POST /resource_requests/{id}/resolve`',
                'spec' => 'Does not say how `bookings` are sent.',
                'api' => 'Each booking is a resource object (`{"type": "bookings", "attributes": {…}}`); a list of attributes answers 400 `json_format_invalid`.',
                'reproduce' => 'POST with `bookings` as a list of attribute objects: 400. As resource objects: 200.',
            ],
            [
                'endpoint' => '`POST /tasks`, `PATCH /tasks/{id}`',
                'spec' => 'Documents `tag_list`.',
                'api' => 'Ignores it: the task answers `"tag_list": []`, and `GET /tags` stays empty.',
                'reproduce' => 'POST or PATCH a task with `"tag_list": ["sdk-check"]` or `"tag_list": "sdk-check"`: 201 or 200 with an empty `tag_list`.',
            ],
            [
                'endpoint' => '`POST /payments`',
                'spec' => 'Does not say that the invoice must have been sent.',
                'api' => '422 `invoice_not_sent` for a finalized invoice without `sent_on`.',
                'reproduce' => 'POST a payment on a finalized invoice that was never sent: 422. After setting `sent_on` on the invoice: 201.',
            ],
            [
                'endpoint' => '`POST /revenue_distributions`',
                'spec' => 'No request body, and nothing about the deal\'s distribution type.',
                'api' => '422 `invalid_revenue_distribution_type` on a deal of the `default` type, which every budget has.',
                'reproduce' => 'POST `deal_id`, `start_on`, `end_on` and `amount_percent` for a budget: 422. For a deal made with `"revenue_distribution_type": "even"`: 201.',
            ],
            [
                'endpoint' => '`PATCH /expenses/{id}/approve`, `PATCH /expenses/{id}/reject`, `PATCH /expenses/{id}/unreject`',
                'spec' => 'Does not say what happens without an expense approval policy.',
                'api' => 'An expense is approved when it is made (approve: 409 `record_already_approved`), rejecting answers 200 and leaves it as it was, and unreject answers 409 `record_already_unrejected`.',
                'reproduce' => 'Without an approval policy: POST an expense, then PATCH approve (409), unapprove (200), reject (200, `rejected` stays false), unreject (409).',
            ],
            [
                'endpoint' => '`DELETE /contracts/{id}`',
                'spec' => 'Documents the delete without conditions.',
                'api' => '409 `contract_not_destroyable` once the contract generated a budget.',
                'reproduce' => 'POST a contract, POST `/contracts/{id}/generate`, then DELETE: 409. Without the generate: 204.',
            ],
            [
                'endpoint' => '`POST /survey_responses`',
                'spec' => 'Does not say that a survey takes no response by default.',
                'api' => '422 `submission_access_denied` while the survey\'s `submission_access` is `nobody`, its default.',
                'reproduce' => 'POST a survey without `submission_access`, then a response: 422. With `"submission_access": "organization"`: 201.',
            ],
            [
                'endpoint' => '`POST /approval_policies`, `PATCH /approval_statuses/{id}/approve`',
                'spec' => 'Does not say that both need the approvals add-on.',
                'api' => 'Without it, the policy answers 409 `approval_addon_disabled`, the status 403 `access_denied`: the same gate, two statuses.',
                'reproduce' => 'On a plan without the add-on: POST a policy (409), PATCH approve on an approval status (403).',
            ],
            [
                'endpoint' => '`GET /reports/*`',
                'spec' => 'Does not document the rate limit of the reports, nor its headers.',
                'api' => 'Ten requests per 30 seconds. The 429 carries `X-RateLimit-Limit: 10`, `X-RateLimit-Remaining: 0` and `X-RateLimit-Reset` in seconds to wait, and errors with `limit` and `period` beside the standard members.',
                'reproduce' => 'Eleven report reads in a burst: the eleventh at the latest answers 429.',
            ],
        ],
        'Structure of the spec' => [
            [
                'endpoint' => '78 resources',
                'spec' => 'The create and the update share one request body, and 54 of those bodies have a `required` list.',
                'api' => 'An update requires nothing, so the list is wrong for every update.',
                'reproduce' => 'Compare `requestBody` of `POST /<resource>` and `PATCH /<resource>/{id}`: the same `$ref`.',
            ],
            [
                'endpoint' => '249 integer enums',
                'spec' => 'Integer `enum`s without names for their values (`"enum": [1, 2]`).',
                'api' => 'Only the descriptions say what 1 and 2 mean, where they say it at all.',
                'reproduce' => 'Search the spec for `"type": "integer"` with an `enum` and no `x-enum-varnames`.',
            ],
            [
                'endpoint' => 'Every operation',
                'spec' => 'The order of parameters changes between downloads of the same spec.',
                'api' => 'Not an API difference, but every comparison of two downloads shows changes that are none.',
                'reproduce' => 'Download the spec twice, a few days apart, and diff the `parameters` lists.',
            ],
        ],
    ],
];
