<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Axyr\Productive\Data\Attributes;
use Axyr\Productive\Generator\Emit\Types;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Recording\Json;
use Axyr\Productive\Generator\Recording\ValueKind;

/**
 * What recorded responses (generator/recorded-types.json) teach the generator, under fixed rules:
 * - an attribute keeps its spec type unless a recorded value is one its lenient reader cannot read;
 * - null and empty values never override, so a resource without data keeps the spec's types;
 * - int and float together widen to float, never the other way round;
 * - attributes and relationships the spec does not document are added, flagged as undocumented;
 * - an attribute only the spec's example has, which no recorded response carried, is left out.
 */
final readonly class RecordedTypes
{
    private string $recordedOn;

    /** @var array<string, int> */
    private array $statuses;

    /** @var array<string, list<string>> */
    private array $rejectedIncludes;

    /** @var array<string, array<string, array<ValueKind>>> Kinds by attribute, by type. */
    private array $attributes;

    /** @var array<string, array<string, array{to_many: bool|null, types: list<string>}>> Relationships by name, by type. */
    private array $relationships;

    /**
     * @param  array<array-key, mixed>  $summary  As written by `composer record:types`; malformed parts are ignored.
     */
    public function __construct(array $summary = [])
    {
        $types = Json::map($summary['types'] ?? null);

        $this->recordedOn = is_string($summary['recorded_on'] ?? null) ? $summary['recorded_on'] : '';
        $this->statuses = array_filter(Json::map($summary['statuses'] ?? null), is_int(...));
        $this->rejectedIncludes = array_map(self::strings(...), Json::map($summary['rejected_includes'] ?? null));
        $this->attributes = array_map(fn(mixed $type): array => array_map(self::kinds(...), Json::map(Json::map($type)['attributes'] ?? null)), $types);
        $this->relationships = array_map(fn(mixed $type): array => array_map(self::relationship(...), Json::map(Json::map($type)['relationships'] ?? null)), $types);
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            return new self();
        }

        // The file exists, so the read cannot fail; the cast only tells static analysis.
        $json = (string) file_get_contents($path); // @pest-mutate-ignore
        $summary = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(is_array($summary) ? $summary : []);
    }

    /**
     * @param  array<string, Attribute>  $attributes  The spec's attributes of the type, keyed by name.
     * @return array<string, Attribute>
     */
    public function attributes(string $type, array $attributes): array
    {
        foreach ($this->attributes[$type] ?? [] as $name => $kinds) {
            $attributes[$name] = isset($attributes[$name])
                ? self::override($attributes[$name], $kinds)
                : self::undocumented($name, $kinds);
        }

        return $attributes;
    }

    /**
     * Adds the relationships recorded with data that the spec does not document. One seen without
     * data has no known cardinality yet; the next recording includes it.
     *
     * @param  array<string, Relationship>  $relationships  The spec's relationships of the type, keyed by name.
     * @param  array<string>  $modelTypes  Types that have a model, the only valid targets.
     * @return array<string, Relationship>
     */
    public function relationships(string $type, array $relationships, array $modelTypes): array
    {
        foreach ($this->relationships[$type] ?? [] as $name => $seen) {
            $relationships[$name] ??= self::undocumentedRelationship($name, $seen, $modelTypes);
        }

        return array_filter($relationships);
    }

    /**
     * @param  array{to_many: bool|null, types: list<string>}  $seen
     * @param  array<string>  $modelTypes
     */
    private static function undocumentedRelationship(string $name, array $seen, array $modelTypes): ?Relationship
    {
        if ($seen['to_many'] === null) {
            return null;
        }

        $target = count($seen['types']) === 1 && in_array($seen['types'][0], $modelTypes, true) ? $seen['types'][0] : null;

        return new Relationship($name, $seen['to_many'], $target, undocumented: true);
    }

    /**
     * Relationships recorded only without data, so without a known cardinality, by type.
     *
     * @return array<string, list<string>>
     */
    public function untypedRelationships(): array
    {
        $untyped = [];

        foreach ($this->relationships as $type => $relationships) {
            foreach ($relationships as $name => $seen) {
                if ($seen['to_many'] === null) {
                    $untyped[$type][] = $name;
                }
            }
        }

        return $untyped;
    }

    /**
     * @return array<string, int>  The status of every recorded call, by recording name.
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /**
     * @return array<string, list<string>>  Relationships the API refused to include, by index operation.
     */
    public function rejectedIncludes(): array
    {
        return $this->rejectedIncludes;
    }

    public function hasType(string $type): bool
    {
        return isset($this->attributes[$type]);
    }

    /**
     * The given attributes that no recorded response of the type carried; none when the type was
     * not recorded, because an empty list proves nothing.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function unseen(string $type, array $names): array
    {
        if (! $this->hasType($type)) {
            return [];
        }

        return array_values(array_filter($names, fn(string $name): bool => ! isset($this->attributes[$type][$name])));
    }

    public function recordedOn(): string
    {
        return $this->recordedOn;
    }

    /**
     * @param  array<ValueKind>  $kinds
     */
    private static function override(Attribute $attribute, array $kinds): Attribute
    {
        $recorded = ValueKind::typeOf($kinds);

        if ($recorded === null || self::readable($attribute, $kinds)) {
            return $attribute;
        }

        return new Attribute(
            name: $attribute->name,
            property: $attribute->property,
            type: $recorded,
            description: $attribute->description,
            required: $attribute->required,
            specType: $attribute->type,
        );
    }

    /**
     * Whether the attribute's lenient reader reads every informative kind that was recorded.
     *
     * @param  array<ValueKind>  $kinds
     */
    private static function readable(Attribute $attribute, array $kinds): bool
    {
        $reader = Types::model($attribute)['reader'];

        foreach ($kinds as $kind) {
            if ($kind->isInformative() && (new Attributes(['value' => $kind->sample()]))->{$reader}('value') === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<ValueKind>  $kinds
     */
    private static function undocumented(string $name, array $kinds): Attribute
    {
        return new Attribute(
            name: $name,
            property: SchemaReader::property($name),
            type: ValueKind::typeOf($kinds) ?? AttributeType::Mixed,
            description: 'Not in the spec: seen in recorded API responses.',
            undocumented: true,
        );
    }

    /**
     * @return array<ValueKind>
     */
    private static function kinds(mixed $kinds): array
    {
        return array_filter(array_map(ValueKind::tryFrom(...), self::strings($kinds)));
    }

    /**
     * @return array{to_many: bool|null, types: list<string>}
     */
    private static function relationship(mixed $seen): array
    {
        $toMany = Json::map($seen)['to_many'] ?? null;

        return ['to_many' => is_bool($toMany) ? $toMany : null, 'types' => self::strings(Json::map($seen)['types'] ?? null)];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
