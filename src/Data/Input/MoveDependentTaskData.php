<?php

declare(strict_types=1);

namespace Axyr\Productive\Data\Input;

use Axyr\Productive\Data\InputData;
use Axyr\Productive\Data\Undefined;

/**
 * Shift a task and the tasks that depend on it by a number of working days.
 */
final readonly class MoveDependentTaskData extends InputData
{
    public function __construct(
        public int $daysCount,
        public bool|Undefined|null $skipRootTask = Undefined::Value,
    ) {}

    public function toAttributes(): array
    {
        return self::filter([
            'days_count' => $this->daysCount,
            'skip_root_task' => $this->skipRootTask,
        ]);
    }
}
