<?php

declare(strict_types=1);

/*
 * The resources `composer generate` writes code for, by API path. Phase 3 enables the rest of
 * the API domain by domain; every operation of an enabled resource must then be generated.
 */
return [
    'activities',
    'attachments',
    'boards',
    'bookings',
    'comments',
    'companies',
    'contact_entries',
    'deal_statuses',
    'discussions',
    'emails',
    'entitlements',
    'events',
    'folders',
    'holiday_calendars',
    'holidays',
    'lost_reasons',
    'memberships',
    'page_versions',
    'pages',
    'people',
    'pipelines',
    'placeholder_usages',
    'placeholders',
    'project_preferences',
    'projects',
    'reports/time_reports',
    'resource_requests',
    'tags',
    'task_dependencies',
    'task_lists',
    'tasks',
    'time_entries',
    'time_entry_versions',
    'time_tracking_policies',
    'timers',
    'timesheets',
    'todos',
    'workflow_statuses',
    'workflows',
];
