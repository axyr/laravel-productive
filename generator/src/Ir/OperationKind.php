<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

enum OperationKind: string
{
    case Index = 'index';
    case Show = 'show';
    case Create = 'create';
    case Update = 'update';
    case Destroy = 'destroy';
    case Action = 'action';
    case CreateBulk = 'create_bulk';
    case UpdateBulk = 'update_bulk';
    case DestroyBulk = 'destroy_bulk';
    case ActionBulk = 'action_bulk';

    public function isBulk(): bool
    {
        return in_array($this, [self::CreateBulk, self::UpdateBulk, self::DestroyBulk, self::ActionBulk], true);
    }
}
