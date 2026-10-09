<?php

declare(strict_types=1);

use Axyr\Productive\Data\Models\Task;
use Axyr\Productive\Http\Expect;
use Axyr\Productive\Http\Method;
use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Axyr\Productive\Resources\Resource;
use Axyr\Productive\Testing\FakeConnector;
use Axyr\Productive\Testing\FakeResponse;

final class ResourceHelpersTestResource extends Resource
{
    protected const string TYPE = 'tasks';

    protected const string PATH = 'tasks';

    public function optional(int $id): ?Task
    {
        return $this->writeOptional(Task::class, Method::Patch, $this->path($id, 'restore'), 'tasks.restore', ['a' => 1], (string) $id);
    }

    public function plain(int $id): Task
    {
        return $this->writePlain(Task::class, Method::Patch, $this->path($id, 'append_markdown'), 'tasks.append_markdown', ['markdown' => '# Hi']);
    }

    public function download(int $id): Response
    {
        return $this->raw(Method::Get, $this->path($id, 'signed_pdf'), 'tasks.signed_pdf');
    }

    public function copyAsBulk(array $data): ?Task
    {
        return $this->writeAsBulk(Task::class, Method::Post, $this->path('copy'), 'tasks.copy', $data);
    }
}

final class ResourceHelpersTestPublicResource extends Resource
{
    protected const string TYPE = 'pages';

    protected const string PATH = 'public/pages';

    protected const bool REQUIRES_ORGANIZATION = false;

    public function download(string $uuid): Response
    {
        return $this->raw(Method::Patch, $this->path($uuid, 'accept'), 'public.pages.accept', ['signed' => true], $uuid);
    }
}

it('returns the model when an optional response has a body, and null when it has none', function () {
    $connector = new FakeConnector([
        'tasks.restore' => FakeResponse::sequence(FakeResponse::resource(['type' => 'tasks', 'id' => '5', 'attributes' => ['title' => 'Back']]), FakeResponse::noContent()),
    ]);
    $resource = new ResourceHelpersTestResource($connector);

    expect($resource->optional(5)?->title)->toBe('Back')
        ->and($resource->optional(5))->toBeNull()
        ->and($connector->recorded()[0]->body)->toBe(['data' => ['type' => 'tasks', 'id' => '5', 'attributes' => ['a' => 1]]])
        ->and($connector->recorded()[0]->expect)->toBe(Expect::Resource);
});

it('sends a plain JSON body', function () {
    $connector = new FakeConnector(['tasks.append_markdown' => FakeResponse::resource(['type' => 'tasks', 'id' => '5'])]);

    $task = (new ResourceHelpersTestResource($connector))->plain(5);

    expect($task->id)->toBe('5')
        ->and($connector->recorded()[0]->body)->toBe(['markdown' => '# Hi'])
        ->and($connector->recorded()[0]->path)->toBe('tasks/5/append_markdown');
});

it('returns the raw response for non-JSON:API endpoints', function () {
    $connector = new FakeConnector(['tasks.signed_pdf' => FakeResponse::json(['data' => ['url' => 'https://example.test/x.pdf']])]);

    $response = (new ResourceHelpersTestResource($connector))->download(5);

    expect($response->json())->toBe(['data' => ['url' => 'https://example.test/x.pdf']])
        ->and($connector->recorded()[0]->expect)->toBe(Expect::Binary)
        ->and($connector->recorded()[0]->body)->toBeNull()
        ->and($connector->recorded()[0]->requiresOrganization)->toBeTrue();
});

it('leaves out the organization header for public resources', function () {
    $connector = new FakeConnector();

    (new ResourceHelpersTestPublicResource($connector))->download('abc');

    /** @var Request $request */
    $request = $connector->recorded()[0];

    expect($request->requiresOrganization)->toBeFalse()
        ->and($request->path)->toBe('public/pages/abc/accept')
        ->and($request->body)->toBe(['data' => ['type' => 'pages', 'id' => 'abc', 'attributes' => ['signed' => true]]]);
});

it('sends one resource as a bulk document', function () {
    $connector = new FakeConnector([
        'tasks.copy' => FakeResponse::sequence(FakeResponse::resource(['type' => 'tasks', 'id' => '9', 'attributes' => ['title' => 'Copy']]), FakeResponse::noContent()),
    ]);
    $resource = new ResourceHelpersTestResource($connector);

    expect($resource->copyAsBulk(['template_id' => 5])?->title)->toBe('Copy')
        ->and($resource->copyAsBulk(['template_id' => 5]))->toBeNull()
        ->and($connector->recorded()[0]->body)->toBe(['data' => [['type' => 'tasks', 'attributes' => ['template_id' => 5]]]])
        ->and($connector->recorded()[0]->contentType)->toBe(Axyr\Productive\Http\ContentType::JsonApiBulk)
        ->and($connector->recorded()[0]->expect)->toBe(Expect::Resource);
});
