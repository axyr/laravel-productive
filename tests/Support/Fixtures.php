<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Responses for generated contract tests: the spec's example where it has one, a minimal
 * document of the right shape where it does not. Resource types are forced to the type the
 * generator decided on, because some spec examples are copied from other resources.
 */
final class Fixtures
{
    public static function response(?string $operationId, string $kind, string $type, int $status = 200): PromiseInterface
    {
        return match ($kind) {
            'no_content' => Http::response(null, 204),
            'raw' => Http::response(['data' => ['url' => 'https://example.test/file.pdf']], $status),
            'collection' => Http::response(['data' => array_map(fn(array $item): array => [...$item, 'type' => $type], self::collection($operationId, $type))], $status),
            default => Http::response(['data' => [...self::resource($operationId, $type), 'type' => $type]], $status),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function resource(?string $operationId, string $type): array
    {
        $data = self::example($operationId)['data'] ?? null;

        return is_array($data) && ! array_is_list($data) && isset($data['id']) ? $data : ['type' => $type, 'id' => '1', 'attributes' => []];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function collection(?string $operationId, string $type): array
    {
        $data = self::example($operationId)['data'] ?? null;

        return is_array($data) && array_is_list($data) && $data !== [] ? $data : [['type' => $type, 'id' => '1', 'attributes' => []]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function example(?string $operationId): array
    {
        if ($operationId === null) {
            return [];
        }

        try {
            return SpecExamples::response($operationId);
        } catch (RuntimeException) {
            return [];
        }
    }
}
