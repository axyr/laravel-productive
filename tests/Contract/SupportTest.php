<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\Fixtures;
use Tests\Support\RequestSchema;
use Tests\Support\SpecExamples;

/*
 * The helpers every contract test relies on. One that always passed would silently switch off
 * hundreds of checks, so each is shown to fail when it should.
 */

it('accepts a valid JSON:API body', function () {
    RequestSchema::assertValid('tasks-create', ['data' => ['type' => 'tasks', 'attributes' => ['title' => 'Plan', 'project_id' => 1, 'task_list_id' => 2]]], 'tasks');
    RequestSchema::assertValidAttributes('tasks-update', ['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 'Plan', 'due_date' => null]]], 'tasks');
    RequestSchema::assertValid('deals-copy-copy', ['data' => [['type' => 'deals', 'attributes' => ['name' => 'Copy', 'template_id' => 1]]]], 'deals');

    expect(true)->toBeTrue();
});

it('rejects bodies that do not match the spec', function (Closure $assertion, string $message) {
    expect($assertion)->toThrow(AssertionFailedError::class, $message);
})->with([
    'missing required attribute' => [fn() => RequestSchema::assertValid('tasks-create', ['data' => ['type' => 'tasks', 'attributes' => ['title' => 'Plan']]], 'tasks'), 'does not match the spec'],
    'wrong value type' => [fn() => RequestSchema::assertValid('tasks-create', ['data' => ['type' => 'tasks', 'attributes' => ['title' => 5, 'project_id' => 1, 'task_list_id' => 2]]], 'tasks'), 'does not match the spec'],
    'no data wrapper' => [fn() => RequestSchema::assertValid('tasks-reposition-reposition', ['move_after_id' => 1], 'tasks'), 'has no "data" resource object'],
    'wrong type' => [fn() => RequestSchema::assertValid('tasks-create', ['data' => ['type' => 'projects', 'attributes' => ['title' => 'Plan', 'project_id' => 1, 'task_list_id' => 2]]], 'tasks'), 'not of type "tasks"'],
    // This bulk schema lists its type and attributes itself, so the schema rejects these first.
    'wrong type in a bulk item' => [fn() => RequestSchema::assertValid('deals-copy-copy', ['data' => [['type' => 'tasks', 'attributes' => ['name' => 'Copy', 'template_id' => 1]]]], 'deals'), 'does not match the spec'],
    'unknown attribute in a bulk item' => [fn() => RequestSchema::assertValid('deals-copy-copy', ['data' => [['type' => 'deals', 'attributes' => ['nam' => 'Copy', 'template_id' => 1]]]], 'deals'), 'does not match the spec'],
    'unknown attribute' => [fn() => RequestSchema::assertValid('tasks-create', ['data' => ['type' => 'tasks', 'attributes' => ['title' => 'Plan', 'project_id' => 1, 'task_list_id' => 2, 'titel' => 'typo']]], 'tasks'), 'attributes the spec does not list: titel'],
    'unknown attribute in an update' => [fn() => RequestSchema::assertValidAttributes('tasks-update', ['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => ['titel' => 'typo']]], 'tasks'), 'attributes the spec does not list: titel'],
    'wrong value in an update' => [fn() => RequestSchema::assertValidAttributes('tasks-update', ['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => ['title' => 5]]], 'tasks'), 'attribute "title" does not match the spec'],
    'unknown key in a plain body' => [fn() => RequestSchema::assertValid('pages-proxy-append-markdown', ['markdown' => '# Hi', 'extra' => 1]), 'attributes the spec does not list: extra'],
]);

it('lists the names a request schema allows', function () {
    expect(SpecExamples::requestAttributeNames('tasks-create'))->toContain('title', 'project_id', 'task_list_id')
        ->and(SpecExamples::requestAttributeNames('deals-copy-copy'))->toContain('name', 'project_id')
        ->and(SpecExamples::requestAttributeNames('pages-proxy-append-markdown'))->toBe(['markdown']);
});

it('picks the success example of an operation', function () {
    $response = Fixtures::response('tasks-show', 'resource', 'tasks', 200)->wait();
    $document = json_decode((string) $response->getBody(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($document['data']['type'])->toBe('tasks')
        ->and($document['data']['id'])->toBe(SpecExamples::response('tasks-show')['data']['id']);
});
