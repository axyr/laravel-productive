<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Generator\Ir\Operation;

/**
 * One request of a write run: a create, an update, an action or a cleanup of a recipe's resource.
 */
final readonly class WriteStep
{
    /**
     * @param  string  $resource  The resource path, e.g. "holidays".
     * @param  string  $type  Its JSON:API type, for the request document.
     * @param  array<string, mixed>|null  $attributes  The request's attributes, with references such as "{created:holiday_calendars}"; null sends no body.
     * @param  bool  $onCreated  Whether the request targets the record this run created ({id} in the path).
     * @param  bool  $creates  Whether the response's ID is the record this run created.
     * @param  bool  $cleanup  Whether it removes what the run created, after every other step.
     */
    public function __construct(
        public Operation $operation,
        public string $resource,
        public string $type,
        public ?array $attributes,
        public bool $onCreated = false,
        public bool $creates = false,
        public bool $cleanup = false,
    ) {}
}
