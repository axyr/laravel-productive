<?php

declare(strict_types=1);

/*
 * GET actions that take query parameters the spec does not document, by operation key, with what
 * each parameter means. The generated method takes them as an array and returns the raw response:
 * these answers are not JSON:API documents, whatever the spec says. Found by calling the API.
 */
return [
    'invoices.preview' => [
        'budget_ids' => 'A list of the IDs of the budgets to invoice.',
        'invoicing_method' => 'uninvoiced_time_and_expenses, uninvoiced_amount or percentage.',
        'display_format' => 'How line items are grouped, such as service, budget, project or task. Required; the API accepts any value.',
        'locale' => 'The language of the line items, such as en. Required for uninvoiced_time_and_expenses.',
        'percentage' => 'The percentage to invoice. Required for the percentage method.',
    ],
];
