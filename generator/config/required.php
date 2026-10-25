<?php

declare(strict_types=1);

/*
 * Attributes the API requires on create although the spec does not mark them required, by input
 * class. Each was found by `composer seed` or `composer record:writes`: the live API answered 422
 * "can't be blank" without it.
 */
return [
    'CreateBankAccountData' => ['number'],
    'CreateDocumentStyleData' => ['styles'],
    'CreateDocumentTypeData' => ['locale'],
    'CreateInvoiceTemplateData' => ['budget_id', 'document_type_id'],
    'CreateRoleData' => ['base_role_id'],
    'CreateTemplateData' => ['model_attributes'],
    'CreateWidgetData' => ['column_position', 'row_position'],
];
