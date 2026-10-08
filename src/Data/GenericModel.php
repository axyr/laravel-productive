<?php

declare(strict_types=1);

namespace Axyr\Productive\Data;

/**
 * Used for resource types without a dedicated model class. All data is available through
 * attribute(), attributes() and related().
 */
final readonly class GenericModel extends Model
{
    protected function hydrate(Attributes $attributes): void {}
}
