<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\Naming;
use RuntimeException;

/**
 * One generated resource method: signature, docblock and body.
 */
final readonly class ResourceMethod
{
    public function __construct(
        private Resource $resource,
        private Operation $operation,
        private string $model,
    ) {}

    /**
     * @return list<string>
     */
    public function imports(): array
    {
        $imports = $this->operation->input === null ? [] : [InputEmitter::NAMESPACE . '\\' . $this->operation->input->class];

        $returnTypes = [
            'PendingQuery' => 'Axyr\\Productive\\Resources\\PendingQuery',
            'ModelCollection' => 'Axyr\\Productive\\Pagination\\ModelCollection',
            'Response' => 'Axyr\\Productive\\Http\\Response',
        ];

        return array_values(array_filter([
            ...$imports,
            $returnTypes[$this->returnType()] ?? null,
            $this->operation->kind === OperationKind::Show ? 'Axyr\\Productive\\Query\\Query' : null,
            str_contains(implode("\n", $this->body()), 'Method::') ? 'Axyr\\Productive\\Http\\Method' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            ...PhpFile::docblock($this->docblock(), '    '),
            '    public function ' . $this->operation->method . '(' . implode(', ', $this->parameters()) . '): ' . $this->returnType(),
            '    {',
            ...array_map(fn(string $line): string => $line === '' ? '' : '        ' . $line, $this->body()),
            '    }',
        ];
    }

    /**
     * @return list<string>
     */
    private function docblock(): array
    {
        $http = $this->operation->httpMethod . ' /' . $this->operation->path . ($this->operation->bulk ? ' (bulk)' : '');
        $summary = $this->operation->summary === '' ? [] : [rtrim($this->operation->summary, '.') . '.', ''];
        $tags = [...$this->parameterDocs(), ...$this->returnDocs()];

        return [...$summary, $http, ...($tags === [] ? [] : ['', ...$tags])];
    }

    /**
     * @return list<string>
     */
    private function parameters(): array
    {
        $parameters = array_map(fn(string $name): string => ($name === 'id' ? 'int|string' : 'string') . ' $' . Naming::camel($name), $this->operation->parameters);

        return [...$parameters, ...match (true) {
            $this->operation->kind === OperationKind::Show => ['array $include = []'],
            $this->operation->kind === OperationKind::CreateBulk => ['array $entries'],
            $this->operation->kind === OperationKind::UpdateBulk => ['array $updates'],
            in_array($this->operation->kind, [OperationKind::DestroyBulk, OperationKind::ActionBulk], true) => ['array $ids'],
            $this->operation->body === BodyKind::Data => ['array $data'],
            $this->operation->input !== null => [$this->operation->input->class . '|array $data'],
            default => [],
        }];
    }

    /**
     * @return list<string>
     */
    private function parameterDocs(): array
    {
        $input = $this->operation->input === null ? '' : $this->operation->input->class . '|';

        return match (true) {
            $this->operation->kind === OperationKind::Show => ['@param  list<string>  $include'],
            $this->operation->kind === OperationKind::CreateBulk => ['@param  list<' . $input . 'array<string, mixed>>  $entries'],
            $this->operation->kind === OperationKind::UpdateBulk => ['@param  array<int|string, ' . $input . 'array<string, mixed>>  $updates  Keyed by ID.'],
            in_array($this->operation->kind, [OperationKind::DestroyBulk, OperationKind::ActionBulk], true) => ['@param  list<int|string>  $ids'],
            $this->operation->body !== BodyKind::None => ['@param  ' . $input . 'array<string, mixed>  $data'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function returnDocs(): array
    {
        return match (true) {
            $this->operation->kind === OperationKind::Index => ['@return PendingQuery<' . $this->model . '>'],
            $this->operation->response === ResponseKind::Collection => ['@return ModelCollection<' . $this->model . '>'],
            default => [],
        };
    }

    private function returnType(): string
    {
        return match ($this->operation->response) {
            ResponseKind::Collection => $this->operation->kind === OperationKind::Index ? 'PendingQuery' : 'ModelCollection',
            ResponseKind::Resource => $this->model,
            ResponseKind::OptionalResource => '?' . $this->model,
            ResponseKind::NoContent => 'void',
            ResponseKind::Raw => 'Response',
        };
    }

    /**
     * @return list<string>
     */
    private function body(): array
    {
        return match ($this->operation->kind) {
            OperationKind::Index => ['return $this->newQuery(' . $this->model . '::class, ' . $this->key() . ');'],
            OperationKind::Show => ['return $this->fetchOne(' . $this->model . '::class, ' . $this->path() . ', ' . $this->key() . ', (new Query())->include(...$include));'],
            OperationKind::CreateBulk => $this->bulkCreate(),
            OperationKind::UpdateBulk => $this->bulkUpdate(),
            OperationKind::DestroyBulk, OperationKind::ActionBulk => ['$this->bulkWithoutResponse(' . $this->method() . ', ' . $this->path() . ', ' . $this->key() . ', $ids);'],
            default => $this->isRead() ? $this->read() : $this->write(),
        };
    }

    /**
     * @return list<string>
     */
    private function read(): array
    {
        return $this->operation->response === ResponseKind::Raw
            ? ['return $this->raw(' . $this->method() . ', ' . $this->path() . ', ' . $this->key() . ');']
            : ['return $this->fetchOne(' . $this->model . '::class, ' . $this->path() . ', ' . $this->key() . ');'];
    }

    /**
     * @return list<string>
     */
    private function write(): array
    {
        $common = [$this->method(), $this->path(), $this->key()];
        $optional = implode(', ', array_filter([...$common, $this->bodyArguments()]));
        $required = implode(', ', [...$common, $this->bodyArguments() === '' ? 'null' : $this->bodyArguments()]);

        return match ($this->operation->response) {
            ResponseKind::Resource => [$this->modelWrite(implode(', ', $common), $required)],
            ResponseKind::OptionalResource => ['return $this->writeOptional(' . $this->model . '::class, ' . $required . ');'],
            ResponseKind::NoContent => ['$this->writeWithoutResponse(' . $optional . ');'],
            ResponseKind::Raw => ['return $this->raw(' . $optional . ');'],
            ResponseKind::Collection => throw new RuntimeException(sprintf('%s: actions returning a collection are not supported yet.', $this->operation->key)),
        };
    }

    private function modelWrite(string $common, string $arguments): string
    {
        return $this->operation->body === BodyKind::Plain
            ? 'return $this->writePlain(' . $this->model . '::class, ' . $common . ', $data);'
            : 'return $this->write(' . $this->model . '::class, ' . $arguments . ');';
    }

    /**
     * "$data, (string) $id" for member writes with a body, "$data" for collection writes, "" without a body.
     */
    private function bodyArguments(): string
    {
        if ($this->operation->body === BodyKind::None) {
            return '';
        }

        $member = self::memberParameter($this->resource, $this->operation);

        return match ($member) {
            null => '$data',
            'id' => '$data, (string) $id',
            default => '$data, $' . Naming::camel($member),
        };
    }

    /**
     * The parameter that identifies the resource, when the path continues with one after the
     * resource ("tasks/{id}/reposition" → "id"); null for collection operations.
     */
    public static function memberParameter(Resource $resource, Operation $operation): ?string
    {
        $rest = substr($operation->path, strlen($resource->path) + 1);

        return preg_match('/^\{([^}]+)\}/', $rest, $match) === 1 ? $match[1] : null;
    }

    /**
     * @return list<string>
     */
    private function bulkCreate(): array
    {
        $type = $this->operation->input === null ? 'array' : $this->operation->input->class . '|array';

        return [
            '$items = array_map(fn(' . $type . ' $entry): array => [\'attributes\' => self::attributes($entry)], $entries);',
            '',
            '/** @var ModelCollection<' . $this->model . '> */',
            'return $this->bulkWrite(' . $this->method() . ', ' . $this->key() . ', $items);',
        ];
    }

    /**
     * @return list<string>
     */
    private function bulkUpdate(): array
    {
        return [
            '$items = [];',
            '',
            'foreach ($updates as $id => $update) {',
            '    $items[] = [\'id\' => (string) $id, \'attributes\' => self::attributes($update)];',
            '}',
            '',
            '/** @var ModelCollection<' . $this->model . '> */',
            'return $this->bulkWrite(' . $this->method() . ', ' . $this->key() . ', $items);',
        ];
    }

    private function isRead(): bool
    {
        return $this->operation->httpMethod === 'GET';
    }

    private function method(): string
    {
        return 'Method::' . ucfirst(strtolower($this->operation->httpMethod));
    }

    private function key(): string
    {
        return Literal::string($this->operation->key);
    }

    /**
     * "$this->path($id, 'reposition')" from "tasks/{id}/reposition".
     */
    private function path(): string
    {
        $rest = trim(substr($this->operation->path, strlen($this->resource->path)), '/');
        $segments = $rest === '' ? [] : explode('/', $rest);
        $arguments = array_map(
            fn(string $segment): string => preg_match('/^\{(.+)\}$/', $segment, $match) === 1 ? '$' . Naming::camel($match[1]) : Literal::string($segment),
            $segments,
        );

        return '$this->path(' . implode(', ', $arguments) . ')';
    }
}
