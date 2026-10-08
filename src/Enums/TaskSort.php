<?php

declare(strict_types=1);

namespace Axyr\Productive\Enums;

/**
 * Sort options for the tasks list.
 */
enum TaskSort: string
{
    case AssigneeName = 'assignee_name';
    case AssigneeNameDesc = '-assignee_name';
    case BillableTime = 'billable_time';
    case BillableTimeDesc = '-billable_time';
    case BoardName = 'board_name';
    case BoardNameDesc = '-board_name';
    case BoardPosition = 'board_position';
    case BoardPositionDesc = '-board_position';
    case ClosedAt = 'closed_at';
    case ClosedAtDesc = '-closed_at';
    case CompanyName = 'company_name';
    case CompanyNameDesc = '-company_name';
    case CreatedAt = 'created_at';
    case CreatedAtDesc = '-created_at';
    case CreatorName = 'creator_name';
    case CreatorNameDesc = '-creator_name';
    case CustomFields = 'custom_fields';
    case CustomFieldsDesc = '-custom_fields';
    case DueDate = 'due_date';
    case DueDateDesc = '-due_date';
    case FolderName = 'folder_name';
    case FolderNameDesc = '-folder_name';
    case FolderPosition = 'folder_position';
    case FolderPositionDesc = '-folder_position';
    case Id = 'id';
    case IdDesc = '-id';
    case InitialEstimate = 'initial_estimate';
    case InitialEstimateDesc = '-initial_estimate';
    case LastActivity = 'last_activity';
    case LastActivityDesc = '-last_activity';
    case LastActivityAt = 'last_activity_at';
    case LastActivityAtDesc = '-last_activity_at';
    case LastActorName = 'last_actor_name';
    case LastActorNameDesc = '-last_actor_name';
    case Number = 'number';
    case NumberDesc = '-number';
    case Placement = 'placement';
    case PlacementDesc = '-placement';
    case ProjectName = 'project_name';
    case ProjectNameDesc = '-project_name';
    case RemainingTime = 'remaining_time';
    case RemainingTimeDesc = '-remaining_time';
    case StartDate = 'start_date';
    case StartDateDesc = '-start_date';
    case TaskListName = 'task_list_name';
    case TaskListNameDesc = '-task_list_name';
    case TaskListPosition = 'task_list_position';
    case TaskListPositionDesc = '-task_list_position';
    case TaskNumber = 'task_number';
    case TaskNumberDesc = '-task_number';
    case Title = 'title';
    case TitleDesc = '-title';
    case UpdatedAt = 'updated_at';
    case UpdatedAtDesc = '-updated_at';
    case WorkedTime = 'worked_time';
    case WorkedTimeDesc = '-worked_time';
    case WorkflowStatusName = 'workflow_status_name';
    case WorkflowStatusNameDesc = '-workflow_status_name';
    case WorkflowStatusPosition = 'workflow_status_position';
    case WorkflowStatusPositionDesc = '-workflow_status_position';
}
