<?php

declare(strict_types=1);

/*
 * Parts of the spec the live API no longer has, found by `composer record` and `composer
 * record:writes`. A removed resource is left out of resources.php; a removed relationship gets no
 * accessor; a removed operation no method. docs/api-differences.md lists them.
 */
return [
    'resources' => [
        'boards' => 'GET /boards returns 404 Route Not Found (recorded 2026-10-09). The official Ruby client dropped its board resource as well; task lists now belong to folders.',
    ],
    'relationships' => [
        'task_lists.board' => 'Task lists now belong to a folder: recorded responses have "folder", not "board".',
        'task_reports.board' => 'The API rejects include=board on task reports (recorded 2026-10-09), and boards no longer exist.',
    ],
    'operations' => [
        'expense_line_items.create' => 'POST /expense_line_items with one resource object answers 404 route_not_found (recorded 2026-10-26): the spec documents the route with a bulk body only, and the single create was synthesized from it. Use createBulk().',
    ],
];
