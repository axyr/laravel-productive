<?php

declare(strict_types=1);

namespace Axyr\Productive\Enums;

/**
 * Grouping dimensions for the time report.
 */
enum TimeReportGroup: string
{
    case BillingType = 'billing_type';
    case Budget = 'budget';
    case Company = 'company';
    case Date = 'date';
    case Day = 'day';
    case Event = 'event';
    case Future = 'future';
    case JobRole = 'job_role';
    case Manager = 'manager';
    case Month = 'month';
    case Organization = 'organization';
    case PeopleCustomFields = 'people_custom_fields';
    case Person = 'person';
    case Project = 'project';
    case Quarter = 'quarter';
    case Service = 'service';
    case ServiceType = 'service_type';
    case StageType = 'stage_type';
    case Subsidiary = 'subsidiary';
    case Week = 'week';
    case Year = 'year';
}
