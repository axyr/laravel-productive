<?php

declare(strict_types=1);

/*
 * Attributes the API requires on create although the spec does not mark them required, by input
 * class. Each was found by `composer seed`: the live API answered 422 "can't be blank" without it.
 */
return [
    'CreateBankAccountData' => ['number'],
    'CreateInvoiceTemplateData' => ['budget_id', 'document_type_id'],
    'CreateTemplateData' => ['model_attributes'],
];
