<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources\Reports;

use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Data\ModelRegistry;

/**
 * Entry point for the /reports endpoints: Productive::reports()->timeReports().
 */
final readonly class Reports
{
    public function __construct(
        private ConnectorInterface $connector,
        private ModelRegistry $registry,
    ) {}

    public function timeReports(): TimeReportResource
    {
        return new TimeReportResource($this->connector, $this->registry);
    }
}
