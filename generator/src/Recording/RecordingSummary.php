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
