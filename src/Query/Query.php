<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

use Axyr\Productive\Exceptions\InvalidQueryException;
use BackedEnum;
use Closure;

/**
 * Builds the query string for a list endpoint: filters, sorting, includes, grouping and pagination.
 */
class Query
{
    public const MAX_PAGE_SIZE = 200;

    protected FilterGroup $filters;

    /** @var list<string> */
    protected array $sorts = [];

    /** @var list<string> */
    protected array $includes = [];

    /** @var list<string> */
    protected array $groups = [];

    protected ?int $pageNumber = null;

    protected ?int $pageSize = null;

    protected ?string $cursor = null;

    public function __construct()
    {
        $this->filters = new FilterGroup();
    }

    /**
     * where('project_id', 42), where('due_date', '>=', '2026-01-01') or where('due_date', Operator::GtEq, $date).
     */
    public function where(string $field, mixed $operatorOrValue, mixed $value = null): static
    {
        func_num_args() === 2
            ? $this->filters->where($field, $operatorOrValue)
            : $this->filters->where($field, $operatorOrValue, $value);

        return $this;
    }

    /**
     * @param  Closure(FilterGroup): mixed  $callback
     */
    public function whereAny(Closure $callback): static
    {
        $this->filters->whereAny($callback);

        return $this;
    }

    /**
     * @param  Closure(FilterGroup): mixed  $callback
     */
    public function whereAll(Closure $callback): static
    {
        $this->filters->whereAll($callback);

        return $this;
    }

    /**
     * sort('due_date', '-created_at') or sort(TaskSort::DueDateDesc). A leading "-" sorts descending.
     */
    public function sort(string|BackedEnum ...$fields): static
    {
        foreach ($fields as $field) {
            $this->sorts[] = self::name($field, 'sort', allowDescending: true);
        }

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        return match (strtolower($direction)) {
            'asc' => $this->sort($field),
            'desc' => $this->sort('-' . $field),
            default => throw new InvalidQueryException(sprintf('Sort direction must be "asc" or "desc", "%s" given.', $direction)),
        };
    }

    /**
     * Sideload related resources, e.g. include('assignee', 'project.company').
     */
    public function include(string ...$relationships): static
    {
        foreach ($relationships as $relationship) {
            $name = self::name($relationship, 'include');

            if (! in_array($name, $this->includes, true)) {
                $this->includes[] = $name;
            }
        }

        return $this;
    }

    /**
     * Group a report, e.g. group(TimeReportGroup::Person).
     */
    public function group(string|BackedEnum ...$dimensions): static
    {
        foreach ($dimensions as $dimension) {
            $this->groups[] = self::name($dimension, 'group');
        }

        return $this;
    }

    public function page(int $number): static
    {
        if ($number < 1) {
            throw new InvalidQueryException('The page number must be 1 or higher.');
        }

        $this->pageNumber = $number;

        return $this;
    }

    public function perPage(int $size): static
    {
        if ($size < 1 || $size > self::MAX_PAGE_SIZE) {
            throw new InvalidQueryException(sprintf('The page size must be between 1 and %d.', self::MAX_PAGE_SIZE));
        }

        $this->pageSize = $size;

        return $this;
    }

    /**
     * Cursor pagination: pass "" for the first page, then the opaque cursor from `links.next`.
     */
    public function after(string $cursor): static
    {
        $this->cursor = $cursor;

        return $this;
    }

    public function hasSort(): bool
    {
        return $this->sorts !== [];
    }

    public function usesCursor(): bool
    {
        return $this->cursor !== null;
    }

    public function pageSize(): ?int
    {
        return $this->pageSize;
    }

    public function toQueryString(): string
    {
        if ($this->cursor !== null && $this->pageNumber !== null) {
            throw new InvalidQueryException('A query cannot use cursor pagination (after) and page numbers (page) at the same time.');
        }

        return QuerySerializer::serialize(
            filters: $this->filters,
            sorts: $this->sorts,
            includes: $this->includes,
            groups: $this->groups,
            page: array_filter(
                ['number' => $this->pageNumber, 'size' => $this->pageSize, 'after' => $this->cursor],
                fn(int|string|null $value): bool => $value !== null,
            ),
        );
    }

    public function __clone()
    {
        $this->filters = clone $this->filters;
    }

    private static function name(string|BackedEnum $value, string $parameter, bool $allowDescending = false): string
    {
        $name = $value instanceof BackedEnum ? (string) $value->value : $value;
        $pattern = $allowDescending ? '/^-?[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/' : '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/';

        if (preg_match($pattern, $name) !== 1) {
            throw new InvalidQueryException(sprintf('Invalid %s value "%s".', $parameter, $name));
        }

        return $name;
    }
}
