<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

/**
 * How far an operation has been checked against the live API, for docs/coverage.md.
 */
enum Coverage: string
{
    /** A GET the recording answered with a success. */
    case Recorded = 'recorded';

    /** A write a live run answered with a success. */
    case Exercised = 'exercised';

    /** The resource's list was empty, so there was no record to fetch. */
    case EmptyList = 'empty list';

    /** The API answered with an error. */
    case Rejected = 'rejected';

    /** The test organization's plan does not include it. */
    case PlanGated = 'plan-gated';

    /** Left out on purpose. */
    case Skipped = 'skipped';

    /** A GET nothing has called yet. */
    case NotRecorded = 'not recorded';

    /** A write nothing has called yet. */
    case NotExercised = 'not exercised';

    public function description(): string
    {
        return match ($this) {
            self::Recorded => 'A GET the recording answered with a success.',
            self::Exercised => 'A write a live run answered with a success.',
            self::EmptyList => 'The list was empty, so there was no record to fetch. Seed the organization and record again.',
            self::Rejected => 'The API answered with an error; the status is shown.',
            self::PlanGated => 'The test organization\'s plan does not include it.',
            self::Skipped => 'Left out on purpose; the reason is shown.',
            self::NotRecorded => 'A GET nothing has called yet.',
            self::NotExercised => 'A write nothing has called yet.',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::EmptyList, self::NotRecorded, self::NotExercised], true);
    }
}
