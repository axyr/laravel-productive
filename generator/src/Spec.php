<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use RuntimeException;

/**
 * Read access to Productive's OpenAPI document, with `$ref` resolution.
 */
final readonly class Spec
{
    public const PATH_PREFIX = '/api/v2/';

    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        private array $document,
    ) {}

    public static function fromFile(string $path): self
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read the OpenAPI spec at %s.', $path));
        }

        return new self(self::map(json_decode($contents, true, flags: JSON_THROW_ON_ERROR)));
    }

    /**
     * Every operation as [path without prefix, HTTP method, operation object].
     *
     * @return list<array{string, string, array<string, mixed>}>
     */
    public function operations(): array
    {
        $operations = [];

        foreach (self::map($this->document['paths'] ?? []) as $path => $item) {
            array_push($operations, ...self::operationsOf(self::stripPrefix($path), self::map($item)));
        }

        return $operations;
    }

    /**
     * Follow `$ref` pointers until a concrete node is reached.
     *
     * @return array<string, mixed>
     */
    public function resolve(mixed $node): array
    {
        $node = self::map($node);

        while (is_string($node['$ref'] ?? null)) {
            $node = $this->pointer($node['$ref']);
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function pointer(string $reference): array
    {
        if (! str_starts_with($reference, '#/')) {
            throw new RuntimeException(sprintf('Only local references are supported, "%s" given.', $reference));
        }

        $node = $this->document;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $node = self::child($node, str_replace(['~1', '~0'], ['/', '~'], $segment), $reference);
        }

        return self::map($node);
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * A list of strings; anything else in the spec or the config is a mistake that must stop generation.
     *
     * @return list<string>
     */
    public static function strings(mixed $value, string $context): array
    {
        $values = is_array($value) ? array_values($value) : [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                throw new RuntimeException(sprintf('%s must only contain strings, %s found.', $context, get_debug_type($item)));
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{string, string, array<string, mixed>}>
     */
    private static function operationsOf(string $path, array $item): array
    {
        $operations = [];

        foreach (array_intersect_key($item, array_flip(self::METHODS)) as $method => $operation) {
            $operations[] = [$path, strtoupper($method), self::map($operation)];
        }

        return $operations;
    }

    private static function child(mixed $node, string $key, string $reference): mixed
    {
        if (! is_array($node) || ! array_key_exists($key, $node)) {
            throw new RuntimeException(sprintf('Unresolvable reference "%s".', $reference));
        }

        return $node[$key];
    }

    private static function stripPrefix(string $path): string
    {
        return str_starts_with($path, self::PATH_PREFIX) ? substr($path, strlen(self::PATH_PREFIX)) : ltrim($path, '/');
    }
}
