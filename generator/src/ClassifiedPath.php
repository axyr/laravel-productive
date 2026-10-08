<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

final readonly class ClassifiedPath
{
    /**
     * @param  list<string>  $parameters  Path parameter names in order.
     * @param  list<string>  $actionSegments  Non-parameter segments after the resource, e.g. ["move_dependent"].
     */
    public function __construct(
        public string $path,
        public string $resource,
        public bool $member,
        public array $parameters,
        public array $actionSegments,
    ) {}

    public function action(): ?string
    {
        return $this->actionSegments === [] ? null : $this->actionName();
    }

    /**
     * The action segments joined, or an empty string when the path has no action.
     */
    public function actionName(): string
    {
        return implode('_', $this->actionSegments);
    }
}
