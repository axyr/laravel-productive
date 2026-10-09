<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\Resource;

/**
 * Writes a resource class: one documented, typed method per operation.
 */
final class ResourceEmitter
{
    public static function emit(Resource $resource, string $model, string $description, string $see): string
    {
        $methods = array_map(fn(Operation $operation): ResourceMethod => new ResourceMethod($resource, $operation, $model), $resource->operations);
        $imports = [
            'Axyr\\Productive\\Resources\\Resource',
            ModelEmitter::NAMESPACE . '\\' . $model,
            ...array_merge(...array_map(fn(ResourceMethod $method): array => $method->imports(), $methods)),
            ...($resource->reportRateLimit ? ['Axyr\\Productive\\Http\\RateLimit'] : []),
        ];

        $body = [
            ...PhpFile::docblock($description === '' ? ['@see ' . $see] : [$description, '', '@see ' . $see]),
            'final class ' . $resource->class . ' extends Resource',
            '{',
            ...self::constants($resource),
            ...array_merge(...array_map(fn(ResourceMethod $method): array => ['', ...$method->lines()], $methods)),
            ...self::rateLimits($resource),
            '}',
        ];

        return PhpFile::render($resource->namespace, $imports, $body);
    }

    /**
     * @return list<string>
     */
    private static function constants(Resource $resource): array
    {
        $constants = [
            '    protected const string TYPE = ' . Literal::string($resource->type) . ';',
            '',
            '    protected const string PATH = ' . Literal::string($resource->path) . ';',
        ];

        if (! $resource->supportsCursor) {
            array_push($constants, '', '    protected const bool SUPPORTS_CURSOR = false;');
        }

        if (self::isPublic($resource)) {
            array_push($constants, '', '    protected const bool REQUIRES_ORGANIZATION = false;');
        }

        return $constants;
    }

    /**
     * @return list<string>
     */
    private static function rateLimits(Resource $resource): array
    {
        if (! $resource->reportRateLimit) {
            return [];
        }

        return ['', '    protected function rateLimits(): array', '    {', '        return [RateLimit::reports()];', '    }'];
    }

    private static function isPublic(Resource $resource): bool
    {
        foreach ($resource->operations as $operation) {
            if (! $operation->requiresOrganization) {
                return true;
            }
        }

        return false;
    }
}
