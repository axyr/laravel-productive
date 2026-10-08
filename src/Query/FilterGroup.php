<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

use Closure;

/**
 * A group of conditions joined by one logical operator. Groups nest to express
 * any combination, e.g. "A and (B or C)".
 */
final class FilterGroup
{
    /** @var list<Condition|FilterGroup> */
    private array $items = [];

    public function __construct(
        public readonly Logical $logical = Logical::And,
    ) {}

    /**
     * where('project_id', 42), where('due_date', '>=', '2026-01-01') or where('due_date', Operator::GtEq, $date).
     */
    public function where(string $field, mixed $operatorOrValue, mixed $value = null): static
    {
        $this->items[] = func_num_args() === 2
            ? new Condition($field, Operator::Eq, $operatorOrValue, explicitOperator: false)
            : new Condition($field, Operator::parse(self::operator($operatorOrValue)), $value);

        return $this;
    }

    /**
     * Matches when any of the conditions added in the callback match.
     *
     * @param  Closure(FilterGroup): mixed  $callback
     */
    public function whereAny(Closure $callback): static
    {
        return $this->nest(Logical::Or, $callback);
    }

    /**
     * Matches when all of the conditions added in the callback match.
     *
     * @param  Closure(FilterGroup): mixed  $callback
     */
    public function whereAll(Closure $callback): static
    {
        return $this->nest(Logical::And, $callback);
    }

    /**
     * @return list<Condition|FilterGroup>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function __clone()
    {
        $this->items = array_map(
            fn(Condition|FilterGroup $item): Condition|FilterGroup => clone $item,
            $this->items,
        );
    }

    /**
     * @param  Closure(FilterGroup): mixed  $callback
     */
    private function nest(Logical $logical, Closure $callback): static
    {
        $group = new self($logical);
        $callback($group);

        if (! $group->isEmpty()) {
            $this->items[] = $group;
        }

        return $this;
    }

    private static function operator(mixed $operator): Operator|string
    {
        return $operator instanceof Operator ? $operator : (is_string($operator) ? $operator : get_debug_type($operator));
    }
}
