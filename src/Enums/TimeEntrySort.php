<?php

declare(strict_types=1);

namespace Axyr\Productive\Enums;

/**
 * Sort options for the time entries list.
 */
enum TimeEntrySort: string
{
    case Date = 'date';
    case DateDesc = '-date';
    case DealName = 'deal_name';
    case DealNameDesc = '-deal_name';
    case PersonName = 'person_name';
    case PersonNameDesc = '-person_name';
    case ServiceName = 'service_name';
    case ServiceNameDesc = '-service_name';
    case UpdatedAt = 'updated_at';
    case UpdatedAtDesc = '-updated_at';
}
