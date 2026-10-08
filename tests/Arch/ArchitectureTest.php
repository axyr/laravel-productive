<?php

declare(strict_types=1);

arch('source uses strict types')
    ->expect('Axyr\\Productive')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->preset()->php();

arch('no security footguns')
    ->preset()->security();

arch('only the connector talks to the HTTP client')
    ->expect('Illuminate\\Http\\Client')
    ->toOnlyBeUsedIn([
        'Axyr\\Productive\\Http\\Connector',
        'Axyr\\Productive\\ProductiveServiceProvider',
    ]);

arch('the JSON:API and data layers do not depend on Laravel')
    ->expect(['Axyr\\Productive\\JsonApi', 'Axyr\\Productive\\Data'])
    ->not->toUse('Illuminate');

arch('value objects are readonly')
    ->expect([
        'Axyr\\Productive\\Config',
        'Axyr\\Productive\\JsonApi',
        'Axyr\\Productive\\Data\\Models',
        'Axyr\\Productive\\Data\\Input',
        'Axyr\\Productive\\Http\\Request',
        'Axyr\\Productive\\Http\\Response',
        'Axyr\\Productive\\Http\\RateLimit',
        'Axyr\\Productive\\Http\\RetryPolicy',
    ])
    ->classes()
    ->toBeReadonly();

arch('models are final and extend the base model')
    ->expect('Axyr\\Productive\\Data\\Models')
    ->toBeFinal()
    ->toExtend('Axyr\\Productive\\Data\\Model');

arch('inputs are final and extend the base input')
    ->expect('Axyr\\Productive\\Data\\Input')
    ->toBeFinal()
    ->toExtend('Axyr\\Productive\\Data\\InputData');

arch('resources are final and extend the base resource')
    ->expect(['Axyr\\Productive\\Resources\\TaskResource', 'Axyr\\Productive\\Resources\\TimeEntryResource', 'Axyr\\Productive\\Resources\\Reports\\TimeReportResource'])
    ->toBeFinal()
    ->toExtend('Axyr\\Productive\\Resources\\Resource');

arch('resource classes are suffixed')
    ->expect('Axyr\\Productive\\Resources')
    ->classes()
    ->toHaveSuffix('Resource')
    ->ignoring(['Axyr\\Productive\\Resources\\PendingQuery', 'Axyr\\Productive\\Resources\\Reports\\Reports']);

arch('enums are string backed')
    ->expect('Axyr\\Productive\\Enums')
    ->toBeStringBackedEnums();

arch('contracts are interfaces')
    ->expect('Axyr\\Productive\\Contracts')
    ->toBeInterfaces();

arch('every exception extends the package base exception')
    ->expect('Axyr\\Productive\\Exceptions')
    ->classes()
    ->toExtend('Axyr\\Productive\\Exceptions\\ProductiveException')
    ->ignoring('Axyr\\Productive\\Exceptions\\ProductiveException');

arch('the base exception is a runtime exception')
    ->expect('Axyr\\Productive\\Exceptions\\ProductiveException')
    ->toExtend('RuntimeException');

arch('connectors implement the connector contract')
    ->expect(['Axyr\\Productive\\Http\\Connector', 'Axyr\\Productive\\Testing\\FakeConnector'])
    ->toImplement('Axyr\\Productive\\Contracts\\ConnectorInterface');

arch('throttles implement the throttle contract')
    ->expect(['Axyr\\Productive\\Http\\CacheThrottle', 'Axyr\\Productive\\Http\\NullThrottle'])
    ->toImplement('Axyr\\Productive\\Contracts\\ThrottleInterface');

arch('the testing namespace is not used by production code')
    ->expect('Axyr\\Productive\\Testing')
    ->toOnlyBeUsedIn(['Axyr\\Productive\\Testing', 'Axyr\\Productive\\ProductiveFacade']);

arch('facade extends the Laravel facade')
    ->expect('Axyr\\Productive\\ProductiveFacade')
    ->toExtend('Illuminate\\Support\\Facades\\Facade');

arch('service provider extends the Laravel service provider')
    ->expect('Axyr\\Productive\\ProductiveServiceProvider')
    ->toExtend('Illuminate\\Support\\ServiceProvider');

arch('the package never depends on the generator')
    ->expect('Axyr\\Productive\\Generator')
    ->toOnlyBeUsedIn('Axyr\\Productive\\Generator');

arch('the generator IR is immutable')
    ->expect('Axyr\\Productive\\Generator\\Ir')
    ->classes()
    ->toBeReadonly()
    ->toBeFinal()
    ->ignoring([
        'Axyr\\Productive\\Generator\\Ir\\AttributeType',
        'Axyr\\Productive\\Generator\\Ir\\OperationKind',
        'Axyr\\Productive\\Generator\\Ir\\ResponseKind',
    ]);

arch('the generator uses strict types')
    ->expect('Axyr\\Productive\\Generator')
    ->toUseStrictTypes();
