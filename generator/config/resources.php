<?php

declare(strict_types=1);

/*
 * The resources `composer generate` writes code for, by API path. Phase 3 enables the rest of
 * the API domain by domain; every operation of an enabled resource must then be generated.
 */
return [
    'reports/time_reports',
    'tasks',
    'time_entries',
];
