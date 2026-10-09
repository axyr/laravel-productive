<?php

declare(strict_types=1);

/*
 * Resources the official Ruby client (productiveio/api_client) knows but the spec does not document.
 * `composer record` probes each with one GET and records only the status code.
 */
return [
    'allocations',
    'automation_runs',
    'automation_versions',
    'automations',
    'bill_items',
    'billability_reports',
    'booking_items',
    'meeting_participants',
    'meetings',
    'organization_invoices',
    'organization_membership_counts',
    'payment_reminders',
    'project_assignments',
    'reports/new_deal_reports',
    'reports/new_salary_reports',
    'reports/new_time_reports',
    'reports/purchase_order_reports',
];
