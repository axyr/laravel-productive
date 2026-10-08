<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\OperationKind;

it('knows which operation kinds are bulk', function (OperationKind $kind, bool $bulk) {
    expect($kind->isBulk())->toBe($bulk);
})->with([
    [OperationKind::Index, false],
    [OperationKind::Show, false],
    [OperationKind::Create, false],
    [OperationKind::Update, false],
    [OperationKind::Destroy, false],
    [OperationKind::Action, false],
    [OperationKind::CreateBulk, true],
    [OperationKind::UpdateBulk, true],
    [OperationKind::DestroyBulk, true],
    [OperationKind::ActionBulk, true],
]);

it('exports attributes without empty members', function () {
    expect((new Attribute('title', 'title', AttributeType::String, 'Name.', true, ['a']))->toArray())
        ->toBe(['name' => 'title', 'property' => 'title', 'type' => 'string', 'description' => 'Name.', 'required' => true, 'enum' => ['a']])
        ->and((new Attribute('a', 'a', AttributeType::Mixed))->toArray())->toBe(['name' => 'a', 'property' => 'a', 'type' => 'mixed']);
});
