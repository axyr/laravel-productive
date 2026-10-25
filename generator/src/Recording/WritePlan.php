<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\Resource;
use RuntimeException;

/**
 * Turns the recipes (generator/config/recipes.php) into the steps of a write run: per recipe a
 * create, an optional update and its actions, and, after all of them, the cleanup of what was
 * created in reverse order. Recipes for anything that leaves Productive or changes the account
 * are refused, so no recipe can send, invite or touch users, sessions or the subscription.
 */
final readonly class WritePlan
{
    /** Resources a write run never touches (PLAN.md, phase 5, step 5). */
    public const array NEVER = [
        'custom_domains', 'einvoice_identities', 'emails', 'integrations', 'invitations', 'organization_memberships',
        'organization_subscriptions', 'organizations', 'passwords', 'sessions', 'users',
    ];

    /** Action names that send something out of Productive. */
    public const array SENDING = ['send', 'deliver', 'invite', 'resend', 'notify'];

    private const array CLEANUPS = ['destroy', 'archive', 'none'];

    /**
     * @param  array<string, array<string, mixed>>  $recipes  By resource path, in run order.
     * @param  list<string>  $resources  Only these resource paths, in recipe order; all of them when empty.
     * @return list<WriteStep>
     *
     * @throws RuntimeException For a recipe of an unknown or forbidden resource, operation or cleanup.
     */
    public static function steps(Api $api, array $recipes, ?int $tier = null, array $resources = []): array
    {
        $selected = array_filter($recipes, fn(array $recipe, string $path): bool => in_array($tier, [null, $recipe['tier'] ?? null], true) && ($resources === [] || in_array($path, $resources, true)), ARRAY_FILTER_USE_BOTH);
        $steps = [];
        $cleanups = [];

        foreach ($selected as $path => $recipe) {
            $target = self::resource($api, $path);
            $steps = [...$steps, ...self::recipeSteps($target, $recipe)];
            $cleanups = [...self::cleanup($target, $recipe), ...$cleanups];
        }

        return [...$steps, ...$cleanups];
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return list<WriteStep>
     */
    private static function recipeSteps(Resource $resource, array $recipe): array
    {
        $steps = [new WriteStep(self::operation($resource, 'create'), $resource->path, $resource->type, self::attributes($recipe['create'] ?? null), creates: true)];

        if (isset($recipe['update'])) {
            $steps[] = new WriteStep(self::operation($resource, 'update'), $resource->path, $resource->type, self::attributes($recipe['update']), onCreated: true);
        }

        return [...$steps, ...self::actionSteps($resource, self::map($recipe['actions'] ?? []))];
    }

    /**
     * @param  array<string, mixed>  $actions  Attributes by action name; null sends no body.
     * @return list<WriteStep>
     */
    private static function actionSteps(Resource $resource, array $actions): array
    {
        $steps = [];

        foreach ($actions as $action => $attributes) {
            if (in_array($action, self::SENDING, true)) {
                throw new RuntimeException(sprintf('The recipe of "%s" may not run "%s": it sends something out of Productive.', $resource->path, $action));
            }

            $operation = self::operation($resource, $action);
            $steps[] = new WriteStep($operation, $resource->path, $resource->type, $attributes === null ? null : self::attributes($attributes), onCreated: str_contains($operation->path, '{id}'));
        }

        return $steps;
    }

    /**
     * The cleanup steps, in their order: one name, or a list such as ["archive", "destroy"] for a
     * resource the API only deletes once it is archived.
     *
     * @param  array<string, mixed>  $recipe
     * @return list<WriteStep>
     */
    private static function cleanup(Resource $resource, array $recipe): array
    {
        $cleanup = $recipe['cleanup'] ?? 'destroy';
        $steps = [];

        foreach (is_array($cleanup) ? $cleanup : [$cleanup] as $name) {
            $checked = self::cleanupName($resource, $name);

            if ($checked !== 'none') {
                $steps[] = new WriteStep(self::operation($resource, $checked), $resource->path, $resource->type, null, onCreated: true, cleanup: true);
            }
        }

        return $steps;
    }

    private static function cleanupName(Resource $resource, mixed $name): string
    {
        if (! is_string($name) || ! in_array($name, self::CLEANUPS, true)) {
            throw new RuntimeException(sprintf('The recipe of "%s" has cleanup "%s": use destroy, archive or none.', $resource->path, is_string($name) ? $name : get_debug_type($name)));
        }

        return $name;
    }

    private static function resource(Api $api, string $path): Resource
    {
        if (in_array($path, self::NEVER, true)) {
            throw new RuntimeException(sprintf('"%s" is never written: it leaves Productive or changes the account.', $path));
        }

        return $api->resource($path) ?? throw new RuntimeException(sprintf('The recipe of "%s" names no resource of the API.', $path));
    }

    private static function operation(Resource $resource, string $action): Operation
    {
        return $resource->operation($resource->path . '.' . $action) ?? throw new RuntimeException(sprintf('"%s" has no "%s" operation.', $resource->path, $action));
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            throw new RuntimeException('A recipe\'s create, update and action attributes must be arrays.');
        }

        return self::map($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        return array_filter(is_array($value) ? $value : [], fn(int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }
}
