<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

/**
 * The first ID seen of every type in recorded bodies, so a resource without an index (a bill, the
 * organization's subscription) can still be fetched by an ID another response carried.
 */
final class SeenIds
{
    /** @var array<string, string> */
    private array $ids = [];

    public function remember(mixed $document): void
    {
        foreach (Json::resources($document) as [$type, $resource]) {
            $id = $resource['id'];

            if (is_string($id) || is_int($id)) {
                $this->ids[$type] ??= (string) $id;
            }
        }
    }

    public function first(string $type): ?string
    {
        return $this->ids[$type] ?? null;
    }
}
