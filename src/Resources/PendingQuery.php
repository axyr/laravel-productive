<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources;

use Axyr\Productive\Data\Model;
use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\Query\Query;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * A query bound to a list endpoint. Build it fluently, then run it with get(), lazy(),
 * all(), first(), count() or paginate().
 *
 * @template TModel of Model
 */
final class PendingQuery extends Query
{
    /**
     * @param  Closure(Query): ModelCollection<Model>  $fetchPage
     * @param  Closure(Query): LazyCollection<int, Model>  $fetchAll
     * @param  Closure(Query, int, int): LengthAwarePaginator<int, Model>  $paginator
     */
    public function __construct(
        private readonly Closure $fetchPage,
        private readonly Closure $fetchAll,
        private readonly Closure $paginator,
    ) {
        parent::__construct();
    }

    /**
     * A single page: the first one, or the one selected with page() / after().
     *
     * @return ModelCollection<TModel>
     */
    public function get(): ModelCollection
    {
        /** @var ModelCollection<TModel> */
        return ($this->fetchPage)($this);
    }

    /**
     * Every matching model, fetched page by page while you iterate.
     *
     * @return LazyCollection<int, TModel>
     */
    public function lazy(): LazyCollection
    {
        /** @var LazyCollection<int, TModel> */
        return ($this->fetchAll)($this);
    }

    /**
     * Every matching model in memory. Prefer lazy() for large collections.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->lazy()->collect();
    }

    /**
     * @return TModel|null
     */
    public function first(): ?Model
    {
        return (clone $this)->perPage(1)->get()->first();
    }

    /**
     * The total number of matching records, read from a one-item page.
     */
    public function count(): int
    {
        $page = (clone $this)->page(1)->perPage(1)->get();

        return $page->totalCount() ?? $page->count();
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 30, int $page = 1): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, TModel> */
        return ($this->paginator)($this, $perPage, $page);
    }
}
