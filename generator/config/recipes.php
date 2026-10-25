<?php

declare(strict_types=1);

/*
 * Recipes of `composer record:writes`, by resource path, in run order: a parent before what refers
 * to it. Each recipe has its tier, the create's attributes, an optional update, actions on the
 * created record (null sends no body) and its cleanup: destroy (the default), archive for a
 * resource the API cannot delete, none, or a list such as ["archive", "destroy"]. A recipe named
 * "<path>#<label>" makes a second record of the resource, for a merge or a copy; `create_with`
 * names the operation that makes it when that is not the create. Attributes may refer to
 * "{created:<recipe>}" (the record this run created), "{first:<path>}" (the first existing record)
 * and "{date:+N}" (N days from today). Every record is named "SDK check …". WritePlan refuses
 * recipes for anything that leaves Productive or changes the account (PLAN.md, phase 5, step 5).
 *
 * Tier 1, settings. Not in it: tags, which the API cannot write, and the merges of deal statuses
 * and service types, whose bodies no attempt found (generator/config/coverage.php). The copies
 * take template_id, the record to copy, which the spec does not document.
 */
return [
    'holiday_calendars' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check calendar'],
        'update' => ['name' => 'SDK check calendar, renamed'],
    ],
    'holidays' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check holiday', 'date' => '{date:+60}', 'holiday_calendar_id' => '{created:holiday_calendars}'],
        'update' => ['name' => 'SDK check holiday, renamed'],
    ],
    'job_roles' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check job role'],
        'update' => ['name' => 'SDK check job role, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    'tax_rates' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check tax 0%', 'subsidiary_id' => '{first:subsidiaries}', 'primary_component_name' => 'SDK check', 'primary_component_value' => 0.0],
        'update' => ['name' => 'SDK check tax 0%, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    'lost_reasons' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check lost reason'],
        'update' => ['name' => 'SDK check lost reason, renamed'],
        'cleanup' => 'archive',
    ],
    'pipelines' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check pipeline'],
        'update' => ['name' => 'SDK check pipeline, renamed'],
    ],
    'deal_statuses' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check deal status', 'pipeline_id' => '{created:pipelines}', 'status_id' => 1, 'probability' => 50],
        'update' => ['name' => 'SDK check deal status, renamed'],
        'actions' => ['archive' => null],
    ],
    'workflows' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check workflow'],
        'update' => ['name' => 'SDK check workflow, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
    ],
    'workflow_statuses' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check workflow status', 'workflow_id' => '{created:workflows}', 'category_id' => 1],
        'update' => ['name' => 'SDK check workflow status, renamed'],
        // Its delete needs target_workflow_status_id (where tasks move to), which the spec does not
        // document and a recipe cannot name; deleting the workflow removes the status.
        'cleanup' => 'none',
    ],
    'workflow_statuses#deleted' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check workflow status to delete', 'workflow_id' => '{created:workflows}', 'category_id' => 1],
        'actions' => ['destroy' => ['target_workflow_status_id' => '{created:workflow_statuses}']],
        'cleanup' => 'none',
    ],
    'custom_field_sections' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check section', 'customizable_type' => 'tasks'],
        'update' => ['name' => 'SDK check section, renamed'],
        'cleanup' => 'archive',
    ],
    'custom_fields' => [
        'tier' => 1,
        // customizable_type is lowercase and plural, as the spec's description says ("tasks, projects, people").
        'create' => ['name' => 'SDK check field', 'data_type_id' => 3, 'customizable_type' => 'tasks', 'section_id' => '{created:custom_field_sections}'],
        'update' => ['name' => 'SDK check field, renamed'],
        'cleanup' => 'archive',
    ],
    'custom_field_options' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check option', 'custom_field_id' => '{created:custom_fields}'],
        'update' => ['name' => 'SDK check option, renamed'],
        'cleanup' => 'archive',
    ],
    'document_styles' => [
        'tier' => 1,
        // styles is required, though the spec does not say so: an object of elements and their properties.
        'create' => ['name' => 'SDK check style', 'styles' => ['elements' => []]],
        'update' => ['name' => 'SDK check style, renamed'],
    ],
    'document_styles#copy' => [
        'tier' => 1,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:document_styles}'],
    ],
    'document_types' => [
        'tier' => 1,
        // locale is required, though the spec does not say so.
        'create' => ['name' => 'SDK check document type', 'exportable_type_id' => 1, 'locale' => 'en_US', 'document_template_id' => 1, 'document_style_id' => '{created:document_styles}'],
        'update' => ['name' => 'SDK check document type, renamed'],
        'cleanup' => 'archive',
    ],
    'document_types#copy' => [
        'tier' => 1,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:document_types}'],
        'cleanup' => 'archive',
    ],
    'filters' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check filter', 'filterable_collection' => 'tasks', 'params' => []],
        'update' => ['name' => 'SDK check filter, renamed'],
    ],
    'dashboards' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check dashboard', 'is_private' => true],
        'update' => ['name' => 'SDK check dashboard, renamed'],
    ],
    'dashboards#copy' => [
        'tier' => 1,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:dashboards}', 'name' => 'SDK check dashboard copy'],
    ],
    'widgets' => [
        'tier' => 1,
        // column_position and row_position are required, though the spec does not say so.
        'create' => ['title' => 'SDK check widget', 'height' => 2, 'width' => 2, 'column_position' => 0, 'row_position' => 0, 'widget_type_id' => 1, 'dashboard_id' => '{created:dashboards}'],
        'update' => ['title' => 'SDK check widget, renamed'],
    ],
    'teams' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check team'],
        'update' => ['name' => 'SDK check team, renamed'],
    ],
    'team_memberships' => [
        'tier' => 1,
        'create' => ['person_id' => '{first:people}', 'team_id' => '{created:teams}'],
    ],
    'time_tracking_policies' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check policy'],
        'update' => ['name' => 'SDK check policy, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        // An active policy cannot be deleted (409 Conflict): it is archived first.
        'cleanup' => ['archive', 'destroy'],
    ],
    'service_types' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check service type'],
        'update' => ['name' => 'SDK check service type, renamed'],
        'cleanup' => 'archive',
    ],
    'roles' => [
        'tier' => 1,
        // base_role_id (the built-in role it extends: 1 admin, 3 staff, ...) is required, though the spec does not say so.
        'create' => ['name' => 'SDK check role', 'user_type_id' => 1, 'base_role_id' => 3],
        'update' => ['name' => 'SDK check role, renamed'],
    ],
    'report_categories' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check report category'],
        'update' => ['name' => 'SDK check report category, renamed'],
    ],
    // model_attributes (a project type, manager and workflow) is required, though the spec does not say so.
    'templates' => [
        'tier' => 1,
        'create' => ['name' => 'SDK check template', 'target_type' => 'project', 'target_id' => '{first:projects}', 'model_attributes' => ['name' => 'SDK check template project', 'project_type_id' => 1, 'project_manager_id' => '{first:people}', 'workflow_id' => '{first:workflows}']],
        'update' => ['name' => 'SDK check template, renamed'],
    ],
];
