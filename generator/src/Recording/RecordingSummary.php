<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Reduces recordings to what the generator may learn from them: the kinds of value each attribute
 * had, the relationships each type had, the includes the API rejected, the attributes a 422 said
 * "can't be blank" for, and the status of every call. It keeps no values, names or IDs, so the
 * summary is safe to commit.
 */
final class RecordingSummary
{
    /** @var array<string, array<string, list<string>>> Kinds seen by attribute, by type. */
    private array $attributes = [];

    /** @var array<string, array<string, array{to_many: bool|null, types: list<string>}>> */
    private array $relationships = [];

    /**
     * Recordings of a write run are named "<run>/<step>.<operation>", e.g.
     * "writes-2026-10-25-122437/028.custom_fields.create". In name order, so the status of a later
     * run wins, while the attributes any run found required are all kept.
     *
     * @param  array<string, array<string, mixed>>  $recordings  Recordings by name, as `composer record` and `composer record:writes` write them.
     * @return array{statuses: array<string, int>, rejected_includes: array<string, list<string>>, required: array<string, list<string>>, types: array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>}
     */
    public function summarize(array $recordings): array
    {
        ksort($recordings);
        $statuses = [];
        $rejected = [];
        $required = [];

        foreach ($recordings as $name => $recording) {
            $key = self::operationKey($name);
            $statuses[$key] = is_int($recording['status'] ?? null) ? $recording['status'] : 0;
            $rejected = self::withRejectedInclude($rejected, $key, $statuses[$key]);
            $required = self::withBlankAttributes($required, $key, $recording);
            $this->observe(self::isSuccessful($statuses[$key]) ? $recording['body'] ?? null : null);
        }

        ksort($statuses);
        ksort($required);

        return ['statuses' => $statuses, 'rejected_includes' => $rejected, 'required' => array_map(self::unique(...), $required), 'types' => $this->types()];
    }

    /**
     * The committed summary with a new one laid over it, so recordings made elsewhere, such as the
     * write runs of another machine, are not lost: a new status replaces an old one, and every
     * required attribute, rejected include, kind and relationship type of either is kept.
     *
     * @param  array<string, mixed>  $previous  As summarize() returned it, read back from JSON.
     * @param  array{statuses: array<string, int>, rejected_includes: array<string, list<string>>, required: array<string, list<string>>, types: array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>}  $current
     * @return array{statuses: array<string, int>, rejected_includes: array<string, list<string>>, required: array<string, list<string>>, types: array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>}
     */
    public static function merge(array $previous, array $current): array
    {
        /** @var array<string, int> $statuses */
        $statuses = [...Json::map($previous['statuses'] ?? null), ...$current['statuses']];
        ksort($statuses);

        return [
            'statuses' => $statuses,
            'rejected_includes' => self::mergeLists(Json::map($previous['rejected_includes'] ?? null), $current['rejected_includes']),
            'required' => self::mergeLists(Json::map($previous['required'] ?? null), $current['required']),
            'types' => self::mergeTypes(Json::map($previous['types'] ?? null), $current['types']),
        ];
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, list<string>>  $current
     * @return array<string, list<string>>
     */
    private static function mergeLists(array $previous, array $current): array
    {
        $merged = $current;

        foreach ($previous as $key => $values) {
            $merged[$key] = self::unique([...Json::strings($values), ...$current[$key] ?? []]);
        }

        ksort($merged);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>  $current
     * @return array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>
     */
    private static function mergeTypes(array $previous, array $current): array
    {
        $merged = $current;

        foreach ($previous as $type => $seen) {
            $seen = Json::map($seen);
            $merged[$type] = [
                'attributes' => self::mergeLists(Json::map($seen['attributes'] ?? null), $current[$type]['attributes'] ?? []),
                'relationships' => self::mergeRelationships(Json::map($seen['relationships'] ?? null), $current[$type]['relationships'] ?? []),
            ];
        }

        ksort($merged);

        return $merged;
    }

    /**
     * A relationship seen as a list stays to-many, and one seen with data keeps what it was seen as.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, array{to_many: bool|null, types: list<string>}>  $current
     * @return array<string, array{to_many: bool|null, types: list<string>}>
     */
    private static function mergeRelationships(array $previous, array $current): array
    {
        $merged = $current;

        foreach ($previous as $name => $relationship) {
            $relationship = Json::map($relationship);
            $now = $current[$name] ?? ['to_many' => null, 'types' => []];
            $merged[$name] = ['to_many' => self::toMany($relationship['to_many'] ?? null, $now['to_many']), 'types' => self::unique([...Json::strings($relationship['types'] ?? null), ...$now['types']])];
        }

        ksort($merged);

        return $merged;
    }

    private static function toMany(mixed $before, ?bool $now): ?bool
    {
        $before = is_bool($before) ? $before : null;

        return $before === true || $now === true ? true : $now ?? $before;
    }

    private static function operationKey(string $name): string
    {
        // The pattern is valid, so preg_replace cannot fail; the cast only tells static analysis.
        return (string) preg_replace('/^(?:[^\/]*\/)?\d+\./', '', $name); // @pest-mutate-ignore
    }

    /**
     * A 422 whose errors say "can't be blank" names attributes the operation requires, unless the
     * error's meta names the condition it applies under, such as a deal's probability in a sales
     * pipeline ("if": "sales_pipeline?"), which a budget does without.
     *
     * @param  array<string, list<string>>  $required
     * @param  array<string, mixed>  $recording
     * @return array<string, list<string>>
     */
    private static function withBlankAttributes(array $required, string $key, array $recording): array
    {
        $errors = ($recording['status'] ?? null) === 422 ? Json::list(Json::map($recording['body'] ?? null)['errors'] ?? null) : [];

        foreach ($errors as $error) {
            $attribute = self::blankAttribute(Json::map($error));
            $required = $attribute === null ? $required : [...$required, $key => [...$required[$key] ?? [], $attribute]];
        }

        return $required;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private static function blankAttribute(array $error): ?string
    {
        $pointer = Json::map($error['source'] ?? null)['pointer'] ?? null;

        if (! self::isUnconditionallyBlank($error) || ! is_string($pointer) || preg_match('/^\/?data\/attributes\/([a-z_]+)$/', $pointer, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private static function isUnconditionallyBlank(array $error): bool
    {
        return ($error['detail'] ?? null) === "can't be blank" && ! isset(Json::map($error['meta'] ?? null)['if']);
    }

    private static function isSuccessful(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    /**
     * A 400 on "<operation>.include.<relationship>" means the API cannot include that relationship.
     *
     * @param  array<string, list<string>>  $rejected
     * @return array<string, list<string>>
     */
    private static function withRejectedInclude(array $rejected, string $name, int $status): array
    {
        if ($status === 400 && preg_match('/^(.+)\.include\.([a-z_]+)$/', $name, $match) === 1) {
            $rejected[$match[1]][] = $match[2];
        }

        return $rejected;
    }

    private function observe(mixed $document): void
    {
        foreach (Json::resources($document) as [$type, $resource]) {
            $this->observeResource($type, $resource);
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function observeResource(string $type, array $resource): void
    {
        $this->attributes[$type] ??= [];
        $this->relationships[$type] ??= [];

        foreach (Json::map($resource['attributes'] ?? null) as $name => $value) {
            $this->attributes[$type][$name][] = ValueKind::detect($value)->value;
        }

        foreach (Json::map($resource['relationships'] ?? null) as $name => $relationship) {
            $this->relationships[$type][$name] = self::observeRelationship($this->relationships[$type][$name] ?? ['to_many' => null, 'types' => []], $relationship);
        }
    }

    /**
     * @param  array{to_many: bool|null, types: list<string>}  $seen
     * @return array{to_many: bool|null, types: list<string>}
     */
    private static function observeRelationship(array $seen, mixed $relationship): array
    {
        $data = Json::map($relationship)['data'] ?? null;

        if (! is_array($data)) {
            return $seen;
        }

        return ['to_many' => $seen['to_many'] === true || array_is_list($data), 'types' => [...$seen['types'], ...self::identifierTypes($data)]];
    }

    /**
     * @param  array<array-key, mixed>  $data  One resource identifier, or a list of them.
     * @return list<string>
     */
    private static function identifierTypes(array $data): array
    {
        $types = [];

        foreach (array_is_list($data) ? $data : [$data] as $identifier) {
            $type = Json::map($identifier)['type'] ?? null;
            $types = is_string($type) ? [...$types, $type] : $types;
        }

        return $types;
    }

    /**
     * @return array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>
     */
    private function types(): array
    {
        $types = [];

        foreach ($this->attributes as $type => $attributes) {
            $types[$type] = ['attributes' => [], 'relationships' => []];

            foreach ($attributes as $name => $kinds) {
                $types[$type]['attributes'][$name] = self::unique($kinds);
            }

            foreach ($this->relationships[$type] as $name => $seen) {
                $types[$type]['relationships'][$name] = ['to_many' => $seen['to_many'], 'types' => self::unique($seen['types'])];
            }

            ksort($types[$type]['attributes']);
            ksort($types[$type]['relationships']);
        }

        ksort($types);

        return $types;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>  Each value once, sorted.
     */
    private static function unique(array $values): array
    {
        $unique = array_unique($values);
        sort($unique);

        return $unique;
    }
}
