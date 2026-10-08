<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Resource
{
    /**
     * @param  string  $path  Path relative to the API base URL, e.g. "tasks" or "reports/time_reports".
     * @param  list<Operation>  $operations
     * @param  list<string>  $sorts  Sort values the index endpoint documents, ascending and descending.
     * @param  list<string>  $groups  Group values for reports.
     */
    public function __construct(
        public string $path,
        public string $class,
        public string $namespace,
        public string $type,
        public ?string $model,
        public array $operations,
        public array $sorts = [],
        public array $groups = [],
        public bool $supportsCursor = true,
        public bool $reportRateLimit = false,
    ) {}

    public function operation(string $key): ?Operation
    {
        foreach ($this->operations as $operation) {
            if ($operation->key === $key) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'class' => $this->namespace . '\\' . $this->class,
            'type' => $this->type,
            'model' => $this->model,
            'supports_cursor' => $this->supportsCursor,
            'report_rate_limit' => $this->reportRateLimit,
            'sorts' => $this->sorts,
            'groups' => $this->groups,
            'operations' => array_map(fn(Operation $operation): array => $operation->toArray(), $this->operations),
        ];
    }
}
