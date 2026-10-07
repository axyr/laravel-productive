<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

class RelationshipNotIncludedException extends ProductiveException
{
    public static function for(string $type, string $relationship): self
    {
        return new self(sprintf(
            'The "%s" relationship of this %s resource was not included in the response. Add ->include(\'%s\') to the query.',
            $relationship,
            $type,
            $relationship,
        ));
    }
}
