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
 * created in reverse order. A recipe named "<path>#<label>" makes a second record of the resource,
 * referenced as "{created:<path>#<label>}", and `create_with` makes it with another operation,
 * such as a copy. Recipes for anything that leaves Productive or changes the account are refused,
 * so no recipe can send, invite or touch users, sessions or the subscription.
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
     * @param  array<string, array<string, mixed>>  $recipes  By resource path, or "<path>#<label>", in run order.
     * @param  list<string>  $resources  Only these recipes, in recipe order; all of them when empty.
     * @return list<WriteStep>
     *
     * @throws RuntimeException For a recipe of an unknown or forbidden resource, operation or cleanup.
     */
    public static function steps(Api $api, array $recipes, ?int $tier = null, array $resources = []): array
    {
        $selected = array_filter($recipes, fn(array $recipe, string $path): bool => in_array($tier, [null, $recipe['tier'] ?? null], true) && ($resources === [] || in_array($path, $resources, true)), ARRAY_FILTER_USE_BOTH);
        $steps = [];
        $cleanups = [];

        foreach ($selected as $name => $recipe) {
            $target = self::resource($api, $name);
            $steps = [...$steps, ...self::recipeSteps($target, $name, $recipe)];
            $cleanups = [...self::cleanup($target, $name, $recipe), ...$cleanups];
        }

        return [...$steps, ...$cleanups];
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return list<WriteStep>
     */
    private static function recipeSteps(Resource $resource, string $name, array $recipe): array
    {
        $creator = is_string($recipe['create_with'] ?? null) ? $recipe['create_with'] : 'create';
        $steps = [new WriteStep(self::operation($resource, $creator), $name, $resource->type, self::attributes($recipe['create'] ?? null), creates: true)];

        if (isset($recipe['update'])) {
            $steps[] = new WriteStep(self::operation($resource, 'update'), $name, $resource->type, self::attributes($recipe['update']), onCreated: true);
        }

        return [...$steps, ...self::actionSteps($resource, $name, self::map($recipe['actions'] ?? []))];
    }

    /**
     * @param  array<string, mixed>  $actions  Attributes by action name; null sends no body.
     * @return list<WriteStep>
     */
    private static function actionSteps(Resource $resource, string $name, array $actions): array
    {
        $steps = [];

        foreach ($actions as $action => $attributes) {
            $operation = self::operation($resource, $action);
            $steps[] = new WriteStep($operation, $name, $resource->type, $attributes === null ? null : self::attributes($attributes), onCreated: str_contains($operation->path, '{id}'));
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
    private static function cleanup(Resource $resource, string $name, array $recipe): array
    {
        $cleanup = $recipe['cleanup'] ?? 'destroy';
        $steps = [];

        foreach (is_array($cleanup) ? $cleanup : [$cleanup] as $step) {
            $checked = self::cleanupName($resource, $step);

            if ($checked !== 'none') {
                $steps[] = new WriteStep(self::operation($resource, $checked), $name, $resource->type, null, onCreated: true, cleanup: true);
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

    private static function resource(Api $api, string $name): Resource
    {
        $path = explode('#', $name)[0];

        if (in_array($path, self::NEVER, true)) {
            throw new RuntimeException(sprintf('"%s" is never written: it leaves Productive or changes the account.', $path));
        }

        return $api->resource($path) ?? throw new RuntimeException(sprintf('The recipe of "%s" names no resource of the API.', $path));
    }

    private static function operation(Resource $resource, string $action): Operation
    {
        if (in_array($action, self::SENDING, true)) {
            throw new RuntimeException(sprintf('The recipe of "%s" may not run "%s": it sends something out of Productive.', $resource->path, $action));
        }

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
