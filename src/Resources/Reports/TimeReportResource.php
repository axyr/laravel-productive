<?php

declare(strict_types=1);

namespace Axyr\Productive\Resources\Reports;

use Axyr\Productive\Data\Models\TimeReport;
use Axyr\Productive\Http\RateLimit;
use Axyr\Productive\Resources\PendingQuery;
use Axyr\Productive\Resources\Resource;

/**
 * Aggregated time data, grouped with ->group(TimeReportGroup::…).
 *
 * Reports use page-number pagination and their own rate limit (10 requests per 30 seconds).
 *
 * @see https://developer.productive.io/reference/resources/reports
 */
final class TimeReportResource extends Resource
{
    protected const string TYPE = 'time_reports';

    protected const string PATH = 'reports/time_reports';

    protected const bool SUPPORTS_CURSOR = false;

    /**
     * GET /reports/time_reports
     *
     * @return PendingQuery<TimeReport>
     */
    public function query(): PendingQuery
    {
        return $this->newQuery(TimeReport::class, 'reports.time_reports.index');
    }

    protected function rateLimits(): array
    {
        return [RateLimit::reports()];
    }
}
