<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\RecordedTypes;

/**
 * The write sections of docs/spec-report.md: the attributes the API requires that the spec does
 * not mark, and the bodies of operations the spec documents without one.
 */
final readonly class SpecReportWrites
{
    /**
     * @param  array<string, list<string>>  $required  Required attributes `composer seed` found, by input class.
     * @param  array<string, array<string, mixed>>  $recipes  The write recipes.
     */
    public function __construct(
        private Api $api,
        private RecordedTypes $recorded,
        private array $required,
        private array $recipes,
    ) {}

    /**
     * @return list<list<string>>
     */
    public function requiredRows(): array
    {
        $byOperation = $this->recorded->required();

        foreach ($this->required as $class => $names) {
            $key = $this->operationOfInput($class)->key ?? $class;
            $byOperation[$key] = [...$byOperation[$key] ?? [], ...$names];
        }

        // A create and its bulk twin share an endpoint, and so one row.
        $byEndpoint = [];

        foreach ($byOperation as $key => $names) {
            $operation = self::operation($this->api, $key);

            if ($operation !== null) {
                $byEndpoint[self::endpoint($operation)] = [$operation, [...$byEndpoint[self::endpoint($operation)][1] ?? [], ...$names]];
            }
        }

        ksort($byEndpoint);

        return array_map(fn(string $endpoint, array $required): array => [$endpoint, self::requiredNames(...$required), 'Leave them out: 422 "can\'t be blank" for each.'], array_keys($byEndpoint), $byEndpoint);
    }

    /**
     * Operations the spec documents without a body that a write run answered with a success,
     * with the attributes its recipe sent.
     *
     * @return array<string, list<string>>  Rows by endpoint.
     */
    public function bodyRows(): array
    {
        $rows = [];

        foreach ($this->recipes as $name => $recipe) {
            foreach (self::recipeBodies(explode('#', $name)[0], $recipe) as $key => $attributes) {
                $operation = self::operation($this->api, $key);
                $status = $this->recorded->statuses()[$key] ?? null;
                $rows[] = self::acceptedUndocumented($operation, $status) ? [self::endpoint($operation), Markdown::codes(array_keys($attributes)), sprintf('Send these attributes: %d.', $status)] : null;
            }
        }

        $rows = array_column(array_filter($rows), null, 0);
        ksort($rows);

        return $rows;
    }

    public static function operation(Api $api, string $key): ?Operation
    {
        $matches = array_filter($api->operations(), fn(Operation $operation): bool => $operation->key === $key);

        return $matches === [] ? null : reset($matches);
    }

    public static function endpoint(Operation $operation): string
    {
        return '`' . $operation->httpMethod . ' /' . $operation->path . '`';
    }

    /**
     * @phpstan-assert-if-true Operation $operation
     * @phpstan-assert-if-true int $status
     */
    private static function acceptedUndocumented(?Operation $operation, ?int $status): bool
    {
        return $operation?->body->documentsNoAttributes() === true && $status !== null && $status >= 200 && $status < 300;
    }

    /**
     * @param  list<string>  $names
     */
    private static function requiredNames(Operation $operation, array $names): string
    {
        $documented = array_map(fn(Attribute $attribute): string => $attribute->name, $operation->input->attributes ?? []);
        // A 422 on a relationship names it without its suffix: "subsidiary" for subsidiary_id.
        $attributes = array_map(fn(string $name): string => ! in_array($name, $documented, true) && in_array($name . '_id', $documented, true) ? $name . '_id' : $name, $names);
        $unique = array_unique($attributes);
        sort($unique);

        return implode(', ', array_map(fn(string $name): string => '`' . $name . '`' . (in_array($name, $documented, true) ? '' : ' (undocumented)'), $unique));
    }

    /**
     * The non-empty attributes a recipe sends, by operation key.
     *
     * @param  array<string, mixed>  $recipe
     * @return array<string, non-empty-array<array-key, mixed>>
     */
    private static function recipeBodies(string $path, array $recipe): array
    {
        $bodies = [$path . '.' . self::creator($recipe) => $recipe['create'] ?? null, $path . '.update' => $recipe['update'] ?? null];

        foreach (self::actions($recipe) as $action => $attributes) {
            $bodies[$path . '.' . $action] = $attributes;
        }

        return array_filter($bodies, fn(mixed $attributes): bool => is_array($attributes) && $attributes !== []);
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    private static function creator(array $recipe): string
    {
        return is_string($recipe['create_with'] ?? null) ? $recipe['create_with'] : 'create';
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<array-key, mixed>
     */
    private static function actions(array $recipe): array
    {
        return is_array($recipe['actions'] ?? null) ? $recipe['actions'] : [];
    }

    private function operationOfInput(string $class): ?Operation
    {
        $matches = array_filter($this->api->operations(), fn(Operation $operation): bool => $operation->input?->class === $class);

        return $matches === [] ? null : reset($matches);
    }
}
