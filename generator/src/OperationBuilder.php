<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\ResponseKind;

final readonly class OperationBuilder
{
    /**
     * @param  array<string, string>  $methodOverrides  PHP method names by operation key, for names the rules get wrong.
     */
    public function __construct(
        private Spec $spec,
        private InputBuilder $inputs,
        private array $methodOverrides = [],
    ) {}

    /**
     * @param  array<string, mixed>  $operation
     */
    public function build(ClassifiedPath $path, string $httpMethod, array $operation, string $model): Operation
    {
        $kind = self::kind($path, $httpMethod, self::isBulk($operation));
        $key = self::key($path, $kind);
        $input = $this->input($kind, $operation, $path->actionName(), $model);

        return new Operation(
            key: $key,
            method: $this->methodOverrides[$key] ?? self::method($kind, $path->actionName()),
            kind: $kind,
            httpMethod: $httpMethod,
            path: $path->path,
            parameters: $path->parameters,
            response: self::response($kind, ResponseShape::fromOperation($this->spec, $operation)),
            bulk: $kind->isBulk(),
            requiresOrganization: self::requiresOrganization($operation),
            operationId: is_string($operation['operationId'] ?? null) ? $operation['operationId'] : null,
            input: $input,
            summary: Spec::string($operation['summary'] ?? null),
            body: self::body($kind, $input, $httpMethod, $path->member),
        );
    }

    public static function kind(ClassifiedPath $path, string $httpMethod, bool $bulk): OperationKind
    {
        return match (true) {
            $path->action() !== null => self::actionKind($path, $bulk),
            $path->member => self::memberKind($httpMethod),
            default => self::collectionKind($httpMethod, $bulk),
        };
    }

    public static function key(ClassifiedPath $path, OperationKind $kind): string
    {
        $action = $path->actionName();

        $name = match ($kind) {
            OperationKind::Action => $action,
            OperationKind::ActionBulk => str_starts_with($action, 'bulk_') ? $action : $action . '_bulk',
            default => $kind->value,
        };

        return str_replace('/', '.', $path->resource) . '.' . $name;
    }

    public static function method(OperationKind $kind, string $action): string
    {
        return match ($kind) {
            OperationKind::Index => 'query',
            OperationKind::Show => 'find',
            OperationKind::Create => 'create',
            OperationKind::Update => 'update',
            OperationKind::Destroy => 'delete',
            OperationKind::CreateBulk => 'bulkCreate',
            OperationKind::UpdateBulk => 'bulkUpdate',
            OperationKind::DestroyBulk => 'bulkDelete',
            OperationKind::Action => Naming::camel($action),
            OperationKind::ActionBulk => Naming::camel(str_starts_with($action, 'bulk_') ? $action : 'bulk_' . $action),
        };
    }

    /**
     * Creates and updates always send a document; when the spec documents no attributes for
     * them, the generated method takes an attribute array instead of an input object. Actions
     * that cannot work without one take an optional attribute array (see needsData()).
     */
    public static function body(OperationKind $kind, ?Input $input, string $httpMethod, bool $member = false): BodyKind
    {
        return match (true) {
            $input !== null => $input->plain ? BodyKind::Plain : BodyKind::Attributes,
            in_array($kind, [OperationKind::Create, OperationKind::Update, OperationKind::CreateBulk, OperationKind::UpdateBulk], true) => BodyKind::Data,
            $kind === OperationKind::Action && self::needsData($httpMethod, $member) => BodyKind::OptionalData,
            default => BodyKind::None,
        };
    }

    /**
     * A body-less POST action, or a body-less write on the whole collection (people/merge), can
     * only work with attributes the spec does not document. Member actions have their ID.
     */
    private static function needsData(string $httpMethod, bool $member): bool
    {
        return $httpMethod === 'POST' || ($httpMethod !== 'GET' && ! $member);
    }

    public static function response(OperationKind $kind, ResponseShape $shape): ResponseKind
    {
        return match ($kind) {
            OperationKind::Index, OperationKind::CreateBulk, OperationKind::UpdateBulk => ResponseKind::Collection,
            OperationKind::DestroyBulk, OperationKind::ActionBulk => ResponseKind::NoContent,
            default => self::responseFromShape($shape),
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    public static function isBulk(array $operation): bool
    {
        $tags = Spec::strings($operation['tags'] ?? [], 'Operation tags');

        return preg_grep('/Bulk/', $tags) !== [] || str_ends_with(Spec::string($operation['operationId'] ?? null), '-bulk');
    }

    private static function responseFromShape(ResponseShape $shape): ResponseKind
    {
        return match (true) {
            $shape->plainJson => ResponseKind::Raw,
            $shape->collection => ResponseKind::Collection,
            $shape->resource => $shape->noContent || $shape->emptyOk ? ResponseKind::OptionalResource : ResponseKind::Resource,
            $shape->emptyOk => ResponseKind::Raw,
            default => ResponseKind::NoContent,
        };
    }

    /**
     * Bulk actions only exist on the collection ("time_entries/approve"), never on a member.
     */
    private static function actionKind(ClassifiedPath $path, bool $bulk): OperationKind
    {
        return $bulk && ! $path->member ? OperationKind::ActionBulk : OperationKind::Action;
    }

    private static function memberKind(string $httpMethod): OperationKind
    {
        return match ($httpMethod) {
            'GET' => OperationKind::Show,
            'DELETE' => OperationKind::Destroy,
            default => OperationKind::Update,
        };
    }

    private static function collectionKind(string $httpMethod, bool $bulk): OperationKind
    {
        return match ($httpMethod) {
            'GET' => OperationKind::Index,
            'POST' => $bulk ? OperationKind::CreateBulk : OperationKind::Create,
            'DELETE' => OperationKind::DestroyBulk,
            default => OperationKind::UpdateBulk,
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function input(OperationKind $kind, array $operation, string $action, string $model): ?Input
    {
        return match ($kind) {
            OperationKind::Create, OperationKind::CreateBulk => $this->inputs->build($operation, 'Create' . $model . 'Data'),
            OperationKind::Update, OperationKind::UpdateBulk => $this->inputs->build($operation, 'Update' . $model . 'Data', allOptional: true),
            OperationKind::Action => $this->inputs->build($operation, Naming::studly($action) . $model . 'Data'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private static function requiresOrganization(array $operation): bool
    {
        $references = array_map(
            fn(mixed $parameter): string => Spec::string(Spec::map($parameter)['$ref'] ?? null),
            is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [],
        );

        return in_array('#/components/parameters/header_organization', $references, true);
    }
}
