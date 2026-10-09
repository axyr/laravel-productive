<?php

declare(strict_types=1);

/*
 * Reports that document filter[after] and filter[before]. `composer record` limits them to the last
 * month: a report without a date range is the most expensive call the API has. The other reports
 * filter on differently named dates, or on none.
 */
return [
    'reports/booking_reports',
    'reports/service_reports',
    'reports/task_reports',
    'reports/time_entry_reports',
    'reports/time_reports',
    'reports/timesheet_reports',
];
