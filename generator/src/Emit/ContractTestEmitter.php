<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;
use Axyr\Productive\Generator\Naming;

/**
 * One contract test per operation: the exact request on the wire, the request body against
 * the spec's schema, and the hydrated result, with the spec's example as the response.
 */
final class ContractTestEmitter
{
    public static function emit(Resource $resource, string $model): string
    {
        $tests = array_map(fn(Operation $operation): array => self::test($resource, $operation, $model), $resource->operations);

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            PhpFile::MARKER,
            '',
            'use Axyr\\Productive\\Data\\Models\\' . $model . ';',
            'use Axyr\\Productive\\Http\\Response;',
            'use Axyr\\Productive\\Pagination\\ModelCollection;',
            'use Axyr\\Productive\\ProductiveFacade as Productive;',
            'use Illuminate\\Http\\Client\\Request as HttpRequest;',
            'use Illuminate\\Support\\Facades\\Http;',
            'use Tests\\Support\\Fixtures;',
            'use Tests\\Support\\RequestSchema;',
            '',
            ...array_merge(...array_map(fn(array $test): array => [...$test, ''], $tests)),
        ];

        return implode("\n", array_slice($lines, 0, -1)) . "\n";
    }

    /**
     * @return list<string>
     */
    private static function test(Resource $resource, Operation $operation, string $model): array
    {
        $status = in_array($operation->kind, [OperationKind::Create, OperationKind::CreateBulk], true) ? 201 : 200;

        return [
            'it(' . Literal::string($operation->key . ': ' . $operation->httpMethod . ' /' . $operation->path) . ', function () {',
            '    fakeHttp([\'*\' => Fixtures::response(' . Literal::export($operation->operationId) . ', ' . Literal::string($operation->response->value) . ', ' . Literal::string($resource->type) . ', ' . $status . ')]);',
            '',
            '    $result = Productive::' . ClientEmitter::accessor($resource) . '()' . self::call($operation) . ';',
            '',
            '    ' . self::resultExpectation($operation, $model),
            '    Http::assertSent(function (HttpRequest $request): bool {',
            ...self::schemaValidation($operation),
            '        return $request->method() === ' . Literal::string($operation->httpMethod),
            '            && urldecode($request->url()) === apiUrl(' . Literal::string(self::url($operation)) . ')',
            '            && ' . self::bodyExpectation($resource, $operation) . ';',
            '    });',
            '});',
        ];
    }

    private static function call(Operation $operation): string
    {
        $parameters = array_map(fn(string $name): string => Literal::string(self::parameterValue($name)), $operation->parameters);
        $sample = Samples::attributes($operation->input, $operation->kind);

        $arguments = match (true) {
            $operation->kind === OperationKind::CreateBulk => [Literal::export([$sample])],
            $operation->kind === OperationKind::UpdateBulk => [Literal::export(['1' => $sample])],
            in_array($operation->kind, [OperationKind::DestroyBulk, OperationKind::ActionBulk], true) => ['[1, 2]'],
            ! in_array($operation->body, [BodyKind::None, BodyKind::OptionalData], true) => [Literal::export($sample)],
            default => [],
        };

        $call = '->' . $operation->method . '(' . implode(', ', [...$parameters, ...$arguments]) . ')';

        return $operation->kind === OperationKind::Index ? $call . '->get()' : $call;
    }

    private static function resultExpectation(Operation $operation, string $model): string
    {
        return match ($operation->response) {
            ResponseKind::Collection => 'expect($result)->toBeInstanceOf(ModelCollection::class);',
            ResponseKind::Resource, ResponseKind::OptionalResource => 'expect($result)->toBeInstanceOf(' . $model . '::class);',
            ResponseKind::NoContent => 'expect($result)->toBeNull();',
            ResponseKind::Raw => 'expect($result)->toBeInstanceOf(Response::class);',
        };
    }

    /**
     * @return list<string>
     */
    private static function schemaValidation(Operation $operation): array
    {
        $operationId = self::schemaOperation($operation);

        if ($operationId === null) {
            return [];
        }

        $assertion = $operation->kind === OperationKind::Update ? 'assertValidAttributes' : 'assertValid';

        return ['        RequestSchema::' . $assertion . '(' . Literal::string($operationId) . ', json_decode($request->body(), true));', ''];
    }

    /**
     * The operation whose request schema the body is validated against: documented, non-bulk
     * operations sending typed attributes or plain JSON. Null when there is nothing to validate.
     */
    private static function schemaOperation(Operation $operation): ?string
    {
        return ! $operation->bulk && in_array($operation->body, [BodyKind::Attributes, BodyKind::Plain, BodyKind::BulkItem], true) ? $operation->operationId : null;
    }

    private static function bodyExpectation(Resource $resource, Operation $operation): string
    {
        $expected = self::expectedBody($resource, $operation);

        if ($expected === null) {
            return '$request->body() === \'\'';
        }

        $contentType = $operation->bulk || $operation->body === BodyKind::BulkItem ? 'application/vnd.api+json; ext=bulk' : 'application/vnd.api+json';

        return '$request->header(\'Content-Type\') === [' . Literal::string($contentType) . ']'
            . "\n            && json_decode(\$request->body(), true) === " . Literal::export($expected);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function expectedBody(Resource $resource, Operation $operation): ?array
    {
        $sample = Samples::attributes($operation->input, $operation->kind);
        $object = fn(?string $id, array $attributes): array => array_filter(['type' => $resource->type, 'id' => $id, 'attributes' => $attributes], fn(mixed $value): bool => $value !== null && $value !== []);

        return match (true) {
            $operation->kind === OperationKind::CreateBulk => ['data' => [$object(null, $sample)]],
            $operation->kind === OperationKind::UpdateBulk => ['data' => [$object('1', $sample)]],
            in_array($operation->kind, [OperationKind::DestroyBulk, OperationKind::ActionBulk], true) => ['data' => [$object('1', []), $object('2', [])]],
            in_array($operation->body, [BodyKind::None, BodyKind::OptionalData], true) => null,
            $operation->body === BodyKind::Plain => $sample,
            $operation->body === BodyKind::BulkItem => ['data' => [$object(null, $sample)]],
            default => ['data' => $object(self::memberId($resource, $operation), $sample)],
        };
    }

    private static function memberId(Resource $resource, Operation $operation): ?string
    {
        $parameter = ResourceMethod::memberParameter($resource, $operation);

        return $parameter === null ? null : self::parameterValue($parameter);
    }

    private static function url(Operation $operation): string
    {
        $placeholders = array_map(fn(string $name): string => '{' . $name . '}', $operation->parameters);

        return str_replace($placeholders, array_map(self::parameterValue(...), $operation->parameters), $operation->path);
    }

    private static function parameterValue(string $name): string
    {
        return $name === 'id' ? '1' : Naming::camel($name) . '-1';
    }
}
