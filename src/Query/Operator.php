<?php

declare(strict_types=1);

namespace Axyr\Productive\Query;

use Axyr\Productive\Exceptions\InvalidQueryException;

/**
 * @see https://developer.productive.io/guides/filtering
 */
enum Operator: string
{
    case Eq = 'eq';
    case NotEq = 'not_eq';
    case Contains = 'contains';
    case NotContain = 'not_contain';
    case Gt = 'gt';
    case GtEq = 'gt_eq';
    case Lt = 'lt';
    case LtEq = 'lt_eq';

    private const SYMBOLS = [
        '=' => 'eq',
        '==' => 'eq',
        '!=' => 'not_eq',
        '<>' => 'not_eq',
        '>' => 'gt',
        '>=' => 'gt_eq',
        '<' => 'lt',
        '<=' => 'lt_eq',
    ];

    /**
     * Accepts an Operator, its API name ("gt_eq") or a comparison symbol (">=").
     */
    public static function parse(self|string $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        return self::tryFrom(self::SYMBOLS[$operator] ?? $operator)
            ?? throw new InvalidQueryException(sprintf(
                'Unknown filter operator "%s". Use one of: %s.',
                $operator,
                implode(', ', array_map(fn(self $case): string => $case->value, self::cases())),
            ));
    }
}
