<?php

declare(strict_types=1);

namespace Axyr\Productive\Pagination;

use Axyr\Productive\Data\Model;
use Illuminate\Support\Collection;

/**
 * One page of models, with the response's pagination meta and links.
 *
 * @template TModel of Model
 *
 * @extends Collection<int, TModel>
 */
final class ModelCollection extends Collection
{
    /** @var array<string, mixed> */
    private array $responseMeta = [];

    /** @var array<string, mixed> */
    private array $responseLinks = [];

    /**
     * @template TNew of Model
     *
     * @param  list<TNew>  $models
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     * @return self<TNew>
     */
    public static function fromPage(array $models, array $meta = [], array $links = []): self
    {
        /** @var self<TNew> $collection */
        $collection = new self($models);
        $collection->responseMeta = $meta;
        $collection->responseLinks = $links;

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->responseMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function links(): array
    {
        return $this->responseLinks;
    }

    public function totalCount(): ?int
    {
        $total = $this->responseMeta['total_count'] ?? null;

        return is_int($total) ? $total : null;
    }
}
