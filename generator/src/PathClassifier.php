<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

/**
 * Splits API paths into a resource, its parameters and an optional action.
 *
 * A resource is the path up to its first parameter ("tasks/{id}" belongs to "tasks"). A path
 * without parameters is an action of its parent when the parent is a resource ("tasks/copy"),
 * and a resource of its own otherwise ("reports/time_reports").
 */
final readonly class PathClassifier
{
    /** @var array<string, string> */
    private array $roots;

    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(array $paths)
    {
        $roots = [];

        foreach ($paths as $path) {
            $prefix = self::beforeFirstParameter($path);

            if ($prefix !== $path) {
                $roots[$prefix] = $prefix;
            }
        }

        $this->roots = self::addParameterlessRoots($roots, $paths);
    }

    public function classify(string $path): ClassifiedPath
    {
        $resource = $this->resourceOf($path);
        $rest = $resource === $path ? [] : explode('/', substr($path, strlen($resource) + 1));
        [$parameters, $actionSegments] = self::split($rest);

        return new ClassifiedPath($path, $resource, isset($rest[0]) && str_starts_with($rest[0], '{'), $parameters, $actionSegments);
    }

    /**
     * @param  list<string>  $segments
     * @return array{list<string>, list<string>} Parameter names and action segments.
     */
    private static function split(array $segments): array
    {
        $parameters = [];
        $actionSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{(.+)\}$/', $segment, $match) === 1) {
                $parameters[] = $match[1];

                continue;
            }

            $actionSegments[] = $segment;
        }

        return [$parameters, $actionSegments];
    }

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = $this->roots;
        sort($roots);

        return $roots;
    }

    private function resourceOf(string $path): string
    {
        $prefix = self::beforeFirstParameter($path);

        return isset($this->roots[$prefix]) ? $prefix : self::parent($prefix);
    }

    /**
     * Parents are classified before their children, so a parameterless path only becomes a
     * root when its parent is not one.
     *
     * @param  array<string, string>  $roots
     * @param  array<int, string>  $paths
     * @return array<string, string>
     */
    private static function addParameterlessRoots(array $roots, array $paths): array
    {
        $parameterless = array_filter($paths, fn(string $path): bool => ! str_contains($path, '{'));
        usort($parameterless, fn(string $a, string $b): int => substr_count($a, '/') <=> substr_count($b, '/'));

        foreach ($parameterless as $path) {
            if (! isset($roots[self::parent($path)])) {
                $roots[$path] = $path;
            }
        }

        return $roots;
    }

    private static function beforeFirstParameter(string $path): string
    {
        $position = strpos($path, '/{');

        return $position === false ? $path : substr($path, 0, $position);
    }

    /**
     * "reports/time_reports" → "reports"; a top-level path has the parent ".", which is never a root.
     */
    private static function parent(string $path): string
    {
        return dirname($path);
    }
}
