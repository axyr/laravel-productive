<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * Reduces recordings to what the generator may learn from them: the kinds of value each attribute
 * had, the relationships each type had, the includes the API rejected and the status of every
 * call. It keeps no values, names or IDs, so the summary is safe to commit.
 */
final class RecordingSummary
{
    /** @var array<string, array<string, list<string>>> Kinds seen by attribute, by type. */
    private array $attributes = [];

    /** @var array<string, array<string, array{to_many: bool|null, types: list<string>}>> */
    private array $relationships = [];

    /**
     * @param  array<string, array<string, mixed>>  $recordings  Recordings by name, as `composer record` writes them.
     * @return array{statuses: array<string, int>, rejected_includes: array<string, list<string>>, types: array<string, array{attributes: array<string, list<string>>, relationships: array<string, array{to_many: bool|null, types: list<string>}>}>}
     */
    public function summarize(array $recordings): array
    {
        ksort($recordings);
        $statuses = array_map(fn(array $recording): int => is_int($recording['status'] ?? null) ? $recording['status'] : 0, $recordings);
        $rejected = [];

        foreach ($recordings as $name => $recording) {
            $rejected = self::withRejectedInclude($rejected, $name, $statuses[$name]);
            $this->observe(self::isSuccessful($statuses[$name]) ? $recording['body'] ?? null : null);
        }

        return ['statuses' => $statuses, 'rejected_includes' => $rejected, 'types' => $this->types()];
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
