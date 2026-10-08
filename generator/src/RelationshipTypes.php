<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

use Illuminate\Support\Str;

/**
 * Works out the JSON:API type a relationship points to. The spec does not say, so the answer
 * comes from, in order: an "owner.relationship" override (which can correct a wrong example),
 * the response examples, a "relationship" override, and the relationship's name.
 */
final readonly class RelationshipTypes
{
    /**
     * @param  array<string, array<string, string>>  $observed  Types seen in examples, by owner type and relationship.
     * @param  array<string, string|null>  $overrides  By "owner.relationship" or "relationship"; null marks a polymorphic relationship.
     * @param  array<string>  $knownTypes
     */
    public function __construct(
        private array $observed,
        private array $overrides,
        private array $knownTypes,
    ) {}

    /**
     * Collect relationship types from every example document in the spec.
     *
     * @param  array<string>  $knownTypes
     * @param  array<string, string|null>  $overrides
     */
    public static function fromExamples(Spec $spec, array $knownTypes, array $overrides): self
    {
        $observed = [];

        foreach (ExampleIndex::resourceObjects($spec) as $resource) {
            $owner = Spec::string($resource['type'] ?? null);
            $observed[$owner] = [...self::observedIn($resource), ...$observed[$owner] ?? []];
        }

        return new self($observed, $overrides, $knownTypes);
    }

    /**
     * Relationship types an example resource object shows.
     *
     * @param  array<string, mixed>  $resource
     * @return array<string, string>
     */
    private static function observedIn(array $resource): array
    {
        $types = array_map(
            fn(mixed $relationship): string => self::typeOf(Spec::map($relationship)['data'] ?? null),
            Spec::map($resource['relationships'] ?? []),
        );

        return array_filter($types, fn(string $type): bool => $type !== '');
    }

    public function resolve(string $ownerType, string $relationship): ?string
    {
        $qualified = $ownerType . '.' . $relationship;

        return match (true) {
            array_key_exists($qualified, $this->overrides) => $this->overrides[$qualified],
            isset($this->observed[$ownerType][$relationship]) => $this->observed[$ownerType][$relationship],
            array_key_exists($relationship, $this->overrides) => $this->overrides[$relationship],
            default => $this->fromName($relationship),
        };
    }

    /**
     * The longest trailing part of the name that pluralizes into a known type:
     * "project" → "projects", "default_tax_rate" → "tax_rates", "custom_field_people" → "people".
     */
    private function fromName(string $relationship): ?string
    {
        $segments = explode('_', $relationship);

        while ($segments !== []) {
            $candidate = Str::plural(implode('_', $segments));

            if (in_array($candidate, $this->knownTypes, true)) {
                return $candidate;
            }

            array_shift($segments);
        }

        return null;
    }

    private static function typeOf(mixed $data): string
    {
        $identifier = Spec::map(is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data);

        return Spec::string($identifier['type'] ?? null);
    }
}
