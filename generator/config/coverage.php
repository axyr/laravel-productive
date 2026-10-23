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
    ],
    'plan_gated' => [],
];
