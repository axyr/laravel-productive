<?php

declare(strict_types=1);

namespace Axyr\Productive\Data\Input;

use Axyr\Productive\Data\InputData;
use Axyr\Productive\Data\Undefined;

/**
 * Where to move a task within its task list or among its sibling subtasks.
 */
final readonly class RepositionTaskData extends InputData
{
    public function __construct(
        public int|string|Undefined|null $moveAfterId = Undefined::Value,
        public int|string|Undefined|null $moveBeforeId = Undefined::Value,
        public bool|Undefined|null $subtask = Undefined::Value,
    ) {}

    public function toAttributes(): array
    {
        return self::filter([
            'move_after_id' => $this->moveAfterId,
            'move_before_id' => $this->moveBeforeId,
            'subtask' => $this->subtask,
        ]);
    }
}
