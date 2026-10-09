<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Reads example documents and request schemas from the vendored OpenAPI spec, so fixtures
 * are Productive's own examples rather than hand-written guesses.
 */
final class SpecExamples
{
    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    /**
     * The example document of an operation's success response.
     *
     * @return array<string, mixed>
     */
    public static function response(string $operationId): array
    {
        $operation = self::operation($operationId);

        foreach ($operation['responses'] as $status => $response) {
            if (str_starts_with((string) $status, '2')) {
                $content = self::resolve($response)['content'] ?? [];
                $schema = self::resolve(reset($content)['schema'] ?? []);

                if (isset($schema['example'])) {
                    return $schema['example'];
                }
            }
        }

        throw new RuntimeException(sprintf('Operation %s has no success response example.', $operationId));
    }

    /**
     * The JSON pointer of an operation's request body schema inside the spec document.
     */
    public static function requestSchemaPointer(string $operationId): string
    {
        [$path, $method] = self::location($operationId);
        $body = self::operation($operationId)['requestBody'] ?? throw new RuntimeException(sprintf('Operation %s has no request body.', $operationId));
        $base = isset($body['$ref'])
            ? $body['$ref']
            : '#/paths/' . rawurlencode(self::escape($path)) . '/' . $method . '/requestBody';
        $contentType = array_key_first(self::resolve($body)['content']);

        return $base . '/content/' . rawurlencode(self::escape($contentType)) . '/schema';
    }

    /**
     * @return array{string, string}  The path and HTTP method of an operation.
     */
    private static function location(string $operationId): array
    {
        foreach (self::spec()['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
                    return [$path, $method];
                }
            }
        }

        throw new RuntimeException(sprintf('Unknown operation %s.', $operationId));
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    public static function path(): string
    {
        return dirname(__DIR__, 2) . '/resources/openapi/productive.json';
    }

    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        return self::$spec ??= json_decode((string) file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private static function operation(string $operationId): array
    {
        foreach (self::spec()['paths'] as $operations) {
            foreach ($operations as $operation) {
                if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
                    return $operation;
                }
            }
        }

        throw new RuntimeException(sprintf('Unknown operation %s.', $operationId));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function resolve(array $node): array
    {
        while (isset($node['$ref'])) {
            $target = self::spec();

            foreach (explode('/', substr($node['$ref'], 2)) as $segment) {
                $target = $target[str_replace(['~1', '~0'], ['/', '~'], $segment)];
            }

            $node = $target;
        }

        return $node;
    }
}
