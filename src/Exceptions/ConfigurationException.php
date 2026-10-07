<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

class ConfigurationException extends ProductiveException
{
    public static function missing(string $key, string $environmentVariable): self
    {
        return new self(sprintf(
            'The Productive %s is not configured. Set %s or publish the config with `php artisan vendor:publish --tag=productive-config`.',
            $key,
            $environmentVariable,
        ));
    }
}
