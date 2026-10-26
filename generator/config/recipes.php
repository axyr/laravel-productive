<?php

declare(strict_types=1);

/*
 * Recipes of `composer record:writes`, by resource path, in run order: a parent before what refers
 * to it. Each recipe has its tier, the create's attributes, an optional update, actions on the
 * created record (null sends no body) and its cleanup: destroy (the default), archive for a
 * resource the API cannot delete, none, or a list such as ["archive", "destroy"]. A recipe named
 * "<path>#<label>" makes a second record of the resource, for a merge or a copy; `create_with`
 * names the operation that makes it when that is not the create, and `on` names the recipe whose
 * record a recipe without a create acts on, later in the run. Attributes may refer to
 * "{created:<recipe>}" (the record this run created), "{first:<path>}" or
 * "{first:<path>?<query>}" (the first existing record, of a filtered list) and "{date:+N}" (N days
 * from today). Every record is named "SDK check …". WritePlan refuses
 * recipes for anything that leaves Productive or changes the account (PLAN.md, phase 5, step 5).
 *
 * Tier 1, settings. Not in it: tags, which the API cannot write, and the merges of deal statuses
 * and service types, whose bodies no attempt found (generator/config/coverage.php). The copies
 * take template_id, the record to copy, which the spec does not document.
 *
 * Tier 2, CRM. Not in it: inviting or resending to a person, activating, deactivating or
 * virtualizing one (a user's seat), regenerating recovery codes, and the merge of people, whose
 * body no attempt found (generator/config/coverage.php).
 *
 * Tier 3, work. Everything is in it. The time entries go on a seeded budget with time approval,
 * and a project's change of workflow maps the seeded default workflow's statuses by their IDs.
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

    'subsidiaries' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check subsidiary'],
        'update' => ['name' => 'SDK check subsidiary, renamed'],
        'cleanup' => 'archive',
    ],
    'companies' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check company', 'default_currency' => 'USD'],
        'update' => ['name' => 'SDK check company, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    // A contact: a person of a client company, never invited, so without a role or an email.
    'people' => [
        'tier' => 2,
        'create' => ['first_name' => 'SDK check', 'last_name' => 'contact', 'company_id' => '{created:companies}'],
        'update' => ['last_name' => 'contact, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    'contact_entries' => [
        'tier' => 2,
        'create' => ['type' => 'phone', 'name' => 'SDK check phone', 'phone' => '0000000000', 'contactable_type' => 'person', 'person_id' => '{created:people}'],
        'update' => ['name' => 'SDK check phone, renamed'],
    ],
    'deals' => [
        'tier' => 2,
        // A deal in a sales pipeline needs a deal status and a probability; a budget needs neither.
        'create' => ['name' => 'SDK check deal', 'deal_type_id' => 2, 'date' => '{date:+0}', 'responsible_id' => '{first:people}', 'company_id' => '{created:companies}', 'currency' => 'USD', 'deal_status_id' => '{first:deal_statuses}', 'probability' => 50],
        'update' => ['name' => 'SDK check deal, renamed'],
    ],
    'deals#budget' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check budget', 'budget' => true, 'deal_type_id' => 2, 'date' => '{date:+0}', 'responsible_id' => '{first:people}', 'company_id' => '{created:companies}', 'currency' => 'USD'],
        'update' => ['name' => 'SDK check budget, renamed'],
        // Only a budget closes and opens; a deal answers 409 "invalid_action".
        'actions' => ['close' => null, 'open' => null],
    ],
    'deals#copy' => [
        'tier' => 2,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:deals}', 'name' => 'SDK check deal copy'],
    ],
    // Only a won deal becomes a budget: an open one is "origin_deal attribute is invalid".
    'deal_statuses#won' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check won', 'pipeline_id' => '{first:pipelines}', 'status_id' => 2, 'probability' => 100],
        // A deleted deal still uses it (409 "deal_status_in_use"), so it is archived.
        'cleanup' => 'archive',
    ],
    'deals#won' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check won deal', 'deal_type_id' => 2, 'date' => '{date:+0}', 'responsible_id' => '{first:people}', 'company_id' => '{created:companies}', 'currency' => 'USD', 'deal_status_id' => '{created:deal_statuses#won}', 'probability' => 100],
    ],
    // The client project the budget belongs to, of the deal's company.
    'projects#budget' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check budget project', 'project_type_id' => 2, 'company_id' => '{created:companies}', 'project_manager_id' => '{first:people}', 'workflow_id' => '{first:workflows}'],
    ],
    'deals#from_origin' => [
        'tier' => 2,
        'create_with' => 'create_from_origin',
        // A budget belongs to a project of the deal's company: any other is "project attribute is invalid".
        'create' => ['origin_deal_id' => '{created:deals#won}', 'project_id' => '{created:projects#budget}'],
    ],
    'deal_cost_rates' => [
        'tier' => 2,
        'create' => ['deal_id' => '{created:deals#budget}', 'person_id' => '{first:people}', 'currency' => 'USD', 'rate_cents' => 5000],
        'update' => ['rate_cents' => 6000],
    ],
    'rate_cards' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check rate card', 'company_id' => '{created:companies}'],
        'update' => ['name' => 'SDK check rate card, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
    ],
    'rate_cards#copy' => [
        'tier' => 2,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:rate_cards}', 'name' => 'SDK check rate card copy'],
    ],
    'prices' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check price', 'company_id' => '{created:companies}', 'rate_card_id' => '{created:rate_cards}', 'unit_id' => 1, 'currency' => 'USD', 'rate' => 100.0],
        'update' => ['name' => 'SDK check price, renamed'],
    ],
    // A proposal needs a document type of kind 2 (proposal), which a {first:} reference cannot pick.
    'document_types#proposal' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check proposal type', 'exportable_type_id' => 2, 'locale' => 'en_US', 'document_template_id' => 1, 'document_style_id' => '{first:document_styles}'],
        'cleanup' => 'archive',
    ],
    // Never sent. document_type_id and status are required, though the spec documents no body.
    'proposals' => [
        'tier' => 2,
        'create' => ['name' => 'SDK check proposal', 'deal_id' => '{created:deals}', 'document_type_id' => '{created:document_types#proposal}', 'status' => 'draft'],
        'update' => ['name' => 'SDK check proposal, renamed'],
        'actions' => ['sync' => null],
    ],

    // The workflow the project changes to, with a status for each of the default workflow's.
    'workflows#target' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check target workflow'],
    ],
    'workflow_statuses#target_open' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check open', 'workflow_id' => '{created:workflows#target}', 'category_id' => 2],
        'cleanup' => 'none',
    ],
    'workflow_statuses#target_closed' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check closed', 'workflow_id' => '{created:workflows#target}', 'category_id' => 3],
        'cleanup' => 'none',
    ],
    // The token's owner (role 1) manages the project, so it is a member that may comment, and it
    // is the person time is tracked and booked for: the first person of the list is virtual.
    'projects' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check project', 'project_type_id' => 2, 'company_id' => '{first:companies?filter[name]=SAMPLE}', 'project_manager_id' => '{first:people?filter[role_id]=1}', 'workflow_id' => '{first:workflows}'],
        'update' => ['name' => 'SDK check project, renamed'],
        'actions' => [
            // The project's manager is subscribed already: subscribing first answers 403.
            'unsubscribe' => null,
            'subscribe' => null,
            'apply_navigation_tabs' => ['template_id' => '{first:projects?filter[template]=true}', 'keep_existing_tabs' => true],
            'archive' => null,
            'restore' => null,
            // The map's keys are the statuses of the default workflow ({first:workflows}): Open and Closed.
            'change_workflow' => ['workflow_id' => '{created:workflows#target}', 'map' => ['186003' => '{created:workflow_statuses#target_open}', '186004' => '{created:workflow_statuses#target_closed}']],
        ],
    ],
    'projects#copy' => [
        'tier' => 3,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:projects}', 'name' => 'SDK check project copy', 'project_type_id' => 2, 'company_id' => '{first:companies?filter[name]=SAMPLE}'],
    ],
    'folders' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check folder', 'project_id' => '{created:projects}'],
        'update' => ['name' => 'SDK check folder, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    'folders#copy' => [
        'tier' => 3,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:folders}', 'name' => 'SDK check folder copy', 'project_id' => '{created:projects}'],
        'actions' => ['reposition' => ['move_before_id' => '{created:folders}'], 'move' => ['project_id' => '{created:projects#copy}']],
        'cleanup' => 'archive',
    ],
    'task_lists' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check task list', 'project_id' => '{created:projects}', 'folder_id' => '{created:folders}'],
        'update' => ['name' => 'SDK check task list, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        'cleanup' => 'archive',
    ],
    'task_lists#copy' => [
        'tier' => 3,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:task_lists}', 'name' => 'SDK check task list copy', 'project_id' => '{created:projects}', 'folder_id' => '{created:folders}'],
        'actions' => ['reposition' => ['move_before_id' => '{created:task_lists}'], 'move' => ['folder_id' => '{created:folders#copy}']],
        'cleanup' => 'archive',
    ],
    'tasks' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check task', 'project_id' => '{created:projects}', 'task_list_id' => '{created:task_lists}', 'start_date' => '{date:+7}', 'due_date' => '{date:+8}'],
        'update' => ['title' => 'SDK check task, renamed'],
    ],
    'tasks#second' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check second task', 'project_id' => '{created:projects}', 'task_list_id' => '{created:task_lists}', 'start_date' => '{date:+9}', 'due_date' => '{date:+10}'],
        'actions' => ['reposition' => ['move_after_id' => '{created:tasks}']],
    ],
    'task_dependencies' => [
        'tier' => 3,
        'create' => ['task_id' => '{created:tasks}', 'dependent_task_id' => '{created:tasks#second}', 'type_id' => 1],
        'update' => ['type_id' => 3],
    ],
    // Shifts the task by one working day; it has no dependent tasks to take along.
    'tasks#moved' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check moved task', 'project_id' => '{created:projects}', 'task_list_id' => '{created:task_lists}', 'start_date' => '{date:+7}', 'due_date' => '{date:+8}'],
        'actions' => ['move_dependent' => ['days_count' => 1]],
    ],
    // A status of the project's workflow, which change_workflow made the target one (404 for another).
    'tasks#copy' => [
        'tier' => 3,
        'create_with' => 'copy',
        'create' => ['template_id' => '{created:tasks}', 'title' => 'SDK check task copy', 'project_id' => '{created:projects}', 'task_list_id' => '{created:task_lists}', 'workflow_status_id' => '{created:workflow_statuses#target_open}', 'private' => false],
    ],
    'todos' => [
        'tier' => 3,
        'create' => ['description' => 'SDK check todo', 'task_id' => '{created:tasks}'],
        'update' => ['description' => 'SDK check todo, renamed'],
    ],
    'comments' => [
        'tier' => 3,
        'create' => ['body' => 'SDK check comment', 'task_id' => '{created:tasks}'],
        'update' => ['body' => 'SDK check comment, edited'],
        'actions' => ['pin' => null, 'unpin' => null, 'add_reaction' => ['reaction' => '👍'], 'remove_reaction' => ['reaction' => '👍']],
    ],
    // The budget the services, bookings and resource requests belong to.
    'deals#work' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check work budget', 'budget' => true, 'deal_type_id' => 2, 'date' => '{date:+0}', 'responsible_id' => '{first:people?filter[role_id]=1}', 'company_id' => '{first:companies?filter[name]=SAMPLE}', 'project_id' => '{created:projects}', 'currency' => 'USD'],
    ],
    'sections' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check section', 'deal_id' => '{created:deals#work}'],
        'update' => ['name' => 'SDK check section, renamed'],
    ],
    'services' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check service', 'deal_id' => '{created:deals#work}', 'section_id' => '{created:sections}', 'limitation_type' => 'no_limitation', 'billing_type_id' => 2, 'unit_id' => 1, 'price' => 0, 'time_tracking_enabled' => true, 'booking_tracking_enabled' => true],
        'update' => ['name' => 'SDK check service, renamed'],
    ],
    'service_assignments' => [
        'tier' => 3,
        'create' => ['service_id' => '{created:services}', 'person_id' => '{first:people?filter[role_id]=1}'],
        'update' => ['person_id' => '{first:people?filter[role_id]=1}'],
    ],
    'bookings' => [
        'tier' => 3,
        'create' => ['person_id' => '{first:people?filter[role_id]=1}', 'service_id' => '{created:services}', 'started_on' => '{date:+30}', 'ended_on' => '{date:+30}', 'booking_method_id' => 1, 'time' => 60, 'note' => 'SDK check booking'],
        'update' => ['note' => 'SDK check booking, edited'],
    ],
    // title, currency, service, booking method and time are required, though the spec documents no body.
    'resource_requests' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check resource request', 'project_id' => '{created:projects}', 'service_id' => '{created:services}', 'currency' => 'USD', 'booking_method_id' => 1, 'time' => 60, 'started_on' => '{date:+31}', 'ended_on' => '{date:+31}'],
        'update' => ['title' => 'SDK check resource request, renamed'],
    ],
    // A canceled, rejected or resolved request is not deleted (409 "request_resolved"); deleting
    // the project removes it.
    'resource_requests#canceled' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check resource request to cancel', 'project_id' => '{created:projects}', 'service_id' => '{created:services}', 'currency' => 'USD', 'booking_method_id' => 1, 'time' => 60, 'started_on' => '{date:+31}', 'ended_on' => '{date:+31}'],
        'actions' => ['cancel' => null],
        'cleanup' => 'none',
    ],
    'resource_requests#rejected' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check resource request to reject', 'project_id' => '{created:projects}', 'service_id' => '{created:services}', 'currency' => 'USD', 'booking_method_id' => 1, 'time' => 60, 'started_on' => '{date:+31}', 'ended_on' => '{date:+31}'],
        'actions' => ['reject' => null],
        'cleanup' => 'none',
    ],
    // Resolving books the request's time for the person: each booking is a resource object, and
    // a bare list of attributes answers 400 "json_format_invalid".
    'resource_requests#resolved' => [
        'tier' => 3,
        'create' => ['title' => 'SDK check resource request to resolve', 'project_id' => '{created:projects}', 'service_id' => '{created:services}', 'currency' => 'USD', 'booking_method_id' => 1, 'time' => 60, 'started_on' => '{date:+31}', 'ended_on' => '{date:+31}'],
        'actions' => ['resolve' => ['bookings' => [['type' => 'bookings', 'attributes' => ['person_id' => '{first:people?filter[role_id]=1}', 'service_id' => '{created:services}', 'started_on' => '{date:+31}', 'ended_on' => '{date:+31}', 'booking_method_id' => '1', 'time' => 60]]]]],
        'cleanup' => 'none',
    ],
    // On the seeded budget "Development": approving needs a budget under a time approval policy,
    // which only the UI assigns (409 "no_time_approval_on_budget" on the run's own).
    'time_entries' => [
        'tier' => 3,
        'create' => ['service_id' => '{first:services?filter[deal_id]=4391271}', 'person_id' => '{first:people?filter[role_id]=1}', 'date' => '{date:+0}', 'time' => 30, 'note' => 'SDK check time entry'],
        'update' => ['note' => 'SDK check time entry, edited'],
    ],
    'time_entries#bulk' => [
        'tier' => 3,
        'create_with' => 'create_bulk',
        'create' => ['service_id' => '{first:services?filter[deal_id]=4391271}', 'person_id' => '{first:people?filter[role_id]=1}', 'date' => '{date:+0}', 'time' => 15, 'note' => 'SDK check bulk time entry'],
        'actions' => ['update_bulk' => ['note' => 'SDK check bulk time entry, edited']],
        'cleanup' => 'none',
    ],
    // Tracks onto the run's time entry; stopping it leaves no other record.
    'timers' => [
        'tier' => 3,
        'create' => ['person_id' => '{first:people?filter[role_id]=1}', 'service_id' => '{first:services?filter[deal_id]=4391271}', 'time_entry_id' => '{created:time_entries}'],
        'actions' => ['stop' => null],
        'cleanup' => 'none',
    ],
    // Submits today. Only a submitted time entry is approved or rejected (409
    // "can_not_approve_unsubmitted_record"), and none is made on a submitted day (422
    // "time_entry_date_submitted"), so the approvals act on the entries after it.
    'timesheets' => [
        'tier' => 3,
        'create' => ['person_id' => '{first:people?filter[role_id]=1}', 'date' => '{date:+0}'],
    ],
    'time_entries#approvals' => [
        'tier' => 3,
        'on' => 'time_entries',
        'actions' => ['approve' => null, 'unapprove' => null, 'reject' => null, 'unreject' => null],
    ],
    'time_entries#bulk_approvals' => [
        'tier' => 3,
        'on' => 'time_entries#bulk',
        'actions' => ['approve_bulk' => null, 'unapprove_bulk' => null, 'destroy_bulk' => null],
    ],
    'events' => [
        'tier' => 3,
        'create' => ['name' => 'SDK check absence', 'event_type_id' => 2, 'limitation_type_id' => 4, 'absence_type' => 'time_off'],
        'update' => ['name' => 'SDK check absence, renamed'],
        'actions' => ['archive' => null, 'restore' => null],
        // Only an archived event is deleted (409 "record_not_archived").
        'cleanup' => ['archive', 'destroy'],
    ],
    'entitlements' => [
        'tier' => 3,
        'create' => ['event_id' => '{created:events}', 'person_id' => '{first:people?filter[role_id]=1}', 'start_date' => '{date:+400}', 'end_date' => '{date:+700}', 'allocated' => 8, 'note' => 'SDK check entitlement'],
        'update' => ['allocated' => 16],
    ],
    // A salary may not overlap another (422 "period_overlap"), and the owner's runs without an end,
    // so this one is a closed month long before it, when no time was tracked.
    'salaries' => [
        'tier' => 3,
        'create' => ['person_id' => '{first:people?filter[role_id]=1}', 'salary_type_id' => 1, 'cost' => 0, 'currency' => 'USD', 'working_hours' => [8, 8, 8, 8, 8, 0, 0, 8, 8, 8, 8, 8, 0, 0], 'started_on' => '2020-01-01', 'ended_on' => '2020-01-31', 'note' => 'SDK check salary'],
        'update' => ['note' => 'SDK check salary, edited'],
    ],
];
