<?php

declare(strict_types=1);

/*
 * Attribute types the recordings cannot show, by "type.attribute". They win over the spec and the
 * recorded values: a discount recorded only as 0 reads as an int, while it holds a percentage or an
 * amount with decimals.
 */
return [
    'prices.discount_amount' => 'float',
    'prices.discount_amount_default' => 'float',
    'prices.discount_amount_normalized' => 'float',
    // Logged as an object for test deliveries; the spec documents the JSON text of a delivery.
    'webhook_logs.payload' => 'json',
];
