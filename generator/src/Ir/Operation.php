<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Ir;

final readonly class Operation
{
    /**
     * @param  string  $key  Stable operation key used by the testing fake, e.g. "tasks.reposition".
     * @param  string|null  $operationId  The spec's operationId; null for operations the spec cannot express.
     * @param  list<string>  $parameters  Path parameters in order, e.g. ["id"].
     */
    public function __construct(
        public string $key,
        public string $method,
        public OperationKind $kind,
        public string $httpMethod,
        public string $path,
        public array $parameters,
        public ResponseKind $response,
        public bool $bulk,
        public bool $requiresOrganization,
        public ?string $operationId,
        public ?Input $input = null,
        public string $summary = '',
    ) {}

    public function isSynthesized(): bool
    {
        return $this->operationId === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'method' => $this->method,
            'kind' => $this->kind->value,
            'http' => $this->httpMethod . ' ' . $this->path,
            'parameters' => $this->parameters,
            'response' => $this->response->value,
            'bulk' => $this->bulk,
            'requires_organization' => $this->requiresOrganization,
            'operation_id' => $this->operationId,
            'input' => $this->input?->class,
            'summary' => $this->summary,
        ];
    }
}
