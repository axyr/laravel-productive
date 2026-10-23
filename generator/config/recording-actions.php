<?php

declare(strict_types=1);

/*
 * GET actions `composer record` may call on the first record of a resource: they only read.
 * `integrations/{id}/check` and `sync` are left out because they act on the integration.
 */
return [
    'invoices.preview',
    'proposals.signed_pdf',
    'proposals.sync_status',
];
