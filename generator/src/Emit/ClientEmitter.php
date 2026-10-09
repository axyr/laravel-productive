<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Naming;

/**
 * The entry points: the client's resource accessors, the report and public groups, the
 * facade docblock and the type-to-model map.
 */
final class ClientEmitter
{
    /**
     * @param  list<Resource>  $resources
     * @return array<string, string>  File contents by path.
     */
    public static function emit(array $resources): array
    {
        $groups = [
            '' => self::inGroup($resources, ''),
            'reports' => self::inGroup($resources, 'reports'),
            'public' => self::inGroup($resources, 'public'),
        ];

        $files = ['src/Concerns/ProvidesResources.php' => self::trait($groups)];

        if ($groups['reports'] !== []) {
            $files['src/Resources/Reports/Reports.php'] = self::groupClass('Axyr\\Productive\\Resources\\Reports', 'Reports', 'Entry point for the /reports endpoints: Productive::reports()->timeReports().', $groups['reports']);
        }

        if ($groups['public'] !== []) {
            $files['src/Resources/Public/PublicResources.php'] = self::groupClass('Axyr\\Productive\\Resources\\Public', 'PublicResources', 'Entry point for the public endpoints, which need no organization: Productive::public()->pages().', $groups['public']);
        }

        return [...$files, 'src/ProductiveFacade.php' => self::facade($groups)];
    }

    /**
     * @param  list<Model>  $models
     * @param  array<string, string>  $aliases  Extra JSON:API types by model class.
     */
    public static function modelMap(array $models, array $aliases): string
    {
        $entries = array_map(fn(Model $model): string => '        ' . $model->class . '::TYPE => ' . $model->class . '::class,', $models);
        $classes = array_map(fn(Model $model): string => $model->class, $models);

        foreach ($aliases as $type => $class) {
            if (in_array($class, $classes, true)) {
                $entries[] = '        ' . Literal::string($type) . ' => ' . $class . '::class,';
            }
        }

        $body = [
            ...PhpFile::docblock(['Built-in JSON:API type to model class map.']),
            'final class ModelMap',
            '{',
            '    /** @var array<string, class-string<Model>> */',
            '    public const MODELS = [',
            ...$entries,
            '    ];',
            '}',
        ];

        return PhpFile::render('Axyr\\Productive\\Data', array_map(fn(string $class): string => ModelEmitter::NAMESPACE . '\\' . $class, $classes), $body);
    }

    public static function accessor(Resource $resource): string
    {
        $name = Naming::camel(basename($resource->path));

        return match (self::group($resource)) {
            'reports' => 'reports()->' . $name,
            'public' => 'public()->' . $name,
            default => $name,
        };
    }

    /**
     * @param  list<Resource>  $resources
     * @return array<Resource>
     */
    private static function inGroup(array $resources, string $group): array
    {
        return array_filter($resources, fn(Resource $resource): bool => self::group($resource) === $group);
    }

    private static function group(Resource $resource): string
    {
        return match (true) {
            str_starts_with($resource->path, 'reports/') => 'reports',
            str_starts_with($resource->path, 'public/') => 'public',
            default => '',
        };
    }

    /**
     * @param  array<string, array<Resource>>  $groups
     */
    private static function trait(array $groups): string
    {
        $methods = [];
        $imports = [];

        foreach ($groups[''] as $resource) {
            $methods[Naming::camel($resource->path)] = $resource->class;
            $imports[] = $resource->namespace . '\\' . $resource->class;
        }

        foreach (['reports' => 'Axyr\\Productive\\Resources\\Reports\\Reports', 'public' => 'Axyr\\Productive\\Resources\\Public\\PublicResources'] as $group => $class) {
            if ($groups[$group] !== []) {
                $methods[$group] = basename(str_replace('\\', '/', $class));
                $imports[] = $class;
            }
        }

        ksort($methods);

        $body = [
            ...PhpFile::docblock(['Accessors for every generated resource. Used by ProductiveClient.']),
            'trait ProvidesResources',
            '{',
            ...self::accessorMethods($methods, '$this->connector(), $this->registry'),
            '}',
        ];

        return PhpFile::render('Axyr\\Productive\\Concerns', $imports, $body);
    }

    /**
     * @param  array<Resource>  $resources
     */
    private static function groupClass(string $namespace, string $class, string $description, array $resources): string
    {
        $methods = [];

        foreach ($resources as $resource) {
            $methods[Naming::camel(basename($resource->path))] = $resource->class;
        }

        ksort($methods);

        $body = [
            ...PhpFile::docblock([$description]),
            'final readonly class ' . $class,
            '{',
            '    public function __construct(',
            '        private ConnectorInterface $connector,',
            '        private ModelRegistry $registry,',
            '    ) {}',
            ...self::accessorMethods($methods, '$this->connector, $this->registry'),
            '}',
        ];

        return PhpFile::render($namespace, ['Axyr\\Productive\\Contracts\\ConnectorInterface', 'Axyr\\Productive\\Data\\ModelRegistry'], $body);
    }

    /**
     * @param  array<string, string>  $methods  Class names by method name.
     * @return list<string>
     */
    private static function accessorMethods(array $methods, string $arguments): array
    {
        $lines = [];

        foreach ($methods as $method => $class) {
            array_push($lines, '', '    public function ' . $method . '(): ' . $class, '    {', '        return new ' . $class . '(' . $arguments . ');', '    }');
        }

        return array_slice($lines, 1);
    }

    /**
     * @param  array<string, array<Resource>>  $groups
     * @return array<string, string>  Fully qualified return classes by accessor name.
     */
    private static function facadeMethods(array $groups): array
    {
        $methods = [];

        foreach ($groups[''] as $resource) {
            $methods[Naming::camel($resource->path)] = '\\' . $resource->namespace . '\\' . $resource->class;
        }

        foreach (['reports' => '\\Axyr\\Productive\\Resources\\Reports\\Reports', 'public' => '\\Axyr\\Productive\\Resources\\Public\\PublicResources'] as $group => $class) {
            if ($groups[$group] !== []) {
                $methods[$group] = $class;
            }
        }

        ksort($methods);

        return $methods;
    }

    /**
     * @param  array<string, array<Resource>>  $groups
     */
    private static function facade(array $groups): string
    {
        $methods = self::facadeMethods($groups);

        $docblock = [
            '@method static \\Axyr\\Productive\\ProductiveClient withOrganization(string $organizationId)',
            '@method static \\Axyr\\Productive\\ProductiveClient withToken(string $token)',
            '@method static \\Axyr\\Productive\\Config\\ProductiveConfig config()',
            '@method static \\Axyr\\Productive\\Contracts\\ConnectorInterface connector()',
            ...array_map(fn(string $method, string $class): string => '@method static ' . $class . ' ' . $method . '()', array_keys($methods), $methods),
            '',
            '@see ProductiveClient',
        ];

        $body = [
            ...PhpFile::docblock($docblock),
            'class ProductiveFacade extends Facade',
            '{',
            '    use FakesProductive;',
            '',
            '    protected static function getFacadeAccessor(): string',
            '    {',
            '        return ProductiveClient::class;',
            '    }',
            '}',
        ];

        return PhpFile::render('Axyr\\Productive', ['Axyr\\Productive\\Testing\\Concerns\\FakesProductive', 'Illuminate\\Support\\Facades\\Facade'], $body);
    }
}
