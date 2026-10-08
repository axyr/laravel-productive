<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Generator\Ir\OperationKind;

/**
 * The response a resource's model is read from: the show response, else the index
 * response, else the first response that carries data.
 */
final readonly class ModelSource
{
    /**
     * @param  array<string, mixed>|null  $schema  Null when no operation of the resource returns data.
     * @param  array<string, mixed>  $example  The example resource object.
     */
    public function __construct(
        public string $type,
        public ?array $schema,
        public array $example,
    ) {}

    /**
     * @param  list<array{ClassifiedPath, string, array<string, mixed>}>  $entries
     */
    public static function pick(Spec $spec, array $entries): self
    {
        $resource = $entries[0][0]->resource;

        foreach (self::byPreference($entries) as $operation) {
            $source = self::fromOperation($spec, $operation, $resource);

            if ($source !== null) {
                return $source;
            }
        }

        return new self(basename($resource), null, []);
    }

    /**
     * Show operations first, then index operations, then the rest, each in spec order.
     *
     * @param  list<array{ClassifiedPath, string, array<string, mixed>}>  $entries
     * @return list<array<string, mixed>>
     */
    private static function byPreference(array $entries): array
    {
        $byKind = ['show' => [], 'index' => [], 'other' => []];

        foreach ($entries as [$path, $method, $operation]) {
            $kind = OperationBuilder::kind($path, $method, OperationBuilder::isBulk($operation));
            $byKind[in_array($kind, [OperationKind::Show, OperationKind::Index], true) ? $kind->value : 'other'][] = $operation;
        }

        return [...$byKind['show'], ...$byKind['index'], ...$byKind['other']];
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private static function fromOperation(Spec $spec, array $operation, string $resource): ?self
    {
        foreach (Spec::map($operation['responses'] ?? []) as $status => $response) {
            $source = self::fromResponse($spec, (string) $status, $spec->resolve($response), $resource);

            if ($source !== null) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function fromResponse(Spec $spec, string $status, array $response, string $resource): ?self
    {
        $schema = str_starts_with($status, '2') ? ResponseShape::fromResponse($spec, $status, $response)->schema : null;

        if ($schema === null) {
            return null;
        }

        $example = ExampleIndex::firstResource($spec, $response);
        $type = Spec::string($example['type'] ?? null);

        return new self($type === '' ? basename($resource) : $type, $schema, $example);
    }
}
