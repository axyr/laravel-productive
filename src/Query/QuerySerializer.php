<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

/**
 * Serializes a query to Productive's query string format.
 *
 * Bracketed keys are kept readable; values are percent-encoded (RFC 3986), so "+" becomes "%2B"
 * as the filtering guide requires. Commas stay literal because they separate list values.
 */
final class QuerySerializer
{
    /**
     * @param  list<string>  $sorts
     * @param  list<string>  $includes
     * @param  list<string>  $groups
     * @param  array<string, int|string>  $page
     */
    public static function serialize(FilterGroup $filters, array $sorts = [], array $includes = [], array $groups = [], array $page = []): string
    {
        $pairs = self::filterPairs($filters);

        foreach (['sort' => $sorts, 'include' => $includes, 'group' => $groups] as $key => $values) {
            if ($values !== []) {
                $pairs[] = [$key, implode(',', $values)];
            }
        }

        foreach ($page as $key => $value) {
            $pairs[] = ['page[' . $key . ']', (string) $value];
        }

        return implode('&', array_map(
            fn(array $pair): string => $pair[0] . '=' . self::encode($pair[1]),
            $pairs,
        ));
    }

    /**
     * A flat AND of distinct fields uses the simple form (`filter[project_id]=1`), which every
     * endpoint supports. Anything else uses the logical form (`filter[$op]=or&filter[0][…]`).
     *
     * @return list<array{string, string}>
     */
    private static function filterPairs(FilterGroup $filters): array
    {
        if (self::isSimple($filters)) {
            return array_map(fn(Condition $condition): array => self::conditionPair('filter', $condition), self::conditions($filters));
        }

        return self::groupPairs('filter', $filters);
    }

    private static function isSimple(FilterGroup $filters): bool
    {
        if ($filters->logical !== Logical::And) {
            return false;
        }

        $conditions = self::conditions($filters);

        if (count($conditions) !== count($filters->items())) {
            return false;
        }

        $fields = array_map(fn(Condition $condition): string => $condition->field, $conditions);

        return count(array_unique($fields)) === count($fields);
    }

    /**
     * @return list<Condition>
     */
    private static function conditions(FilterGroup $group): array
    {
        $conditions = [];

        foreach ($group->items() as $item) {
            if ($item instanceof Condition) {
                $conditions[] = $item;
            }
        }

        return $conditions;
    }

    /**
     * @return array{string, string}
     */
    private static function conditionPair(string $prefix, Condition $condition, bool $forceOperator = false): array
    {
        $operator = $condition->explicitOperator || $forceOperator ? '[' . $condition->operator->value . ']' : '';

        return [$prefix . $condition->path() . $operator, $condition->value];
    }

    /**
     * @return list<array{string, string}>
     */
    private static function groupPairs(string $prefix, FilterGroup $group): array
    {
        $pairs = [[$prefix . '[$op]', $group->logical->value]];

        foreach ($group->items() as $index => $item) {
            $itemPrefix = $prefix . '[' . $index . ']';

            if ($item instanceof FilterGroup) {
                array_push($pairs, ...self::groupPairs($itemPrefix, $item));

                continue;
            }

            $pairs[] = self::conditionPair($itemPrefix, $item, forceOperator: true);
        }

        return $pairs;
    }

    private static function encode(string $value): string
    {
        return str_replace('%2C', ',', rawurlencode($value));
    }
}
