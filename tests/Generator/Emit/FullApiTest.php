<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\CodeGenerator;
use Axyr\Productive\Generator\Emit\PhpFile;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\IrBuilder;
use PhpParser\ParserFactory;
use Tests\Support\SpecExamples;

/**
 * Every file the generator writes when the whole API is enabled.
 *
 * @return array<string, string>
 */
function fullApi(): array
{
    static $files = null;

    return $files ??= generateFullApi();
}

/**
 * Uncached, so a test calling it executes (and is credited with covering) every emitter.
 *
 * @return array<string, string>
 */
function generateFullApi(): array
{
    $config = dirname(__DIR__, 3) . '/generator/config';
    $api = IrBuilder::fromFiles(SpecExamples::path(), $config)->build();

    return (new CodeGenerator($api, array_map(fn(Resource $resource): string => $resource->path, $api->resources), require $config . '/type-aliases.php'))->files();
}

function generated(string $path): string
{
    return fullApi()[$path] ?? throw new RuntimeException('Not generated: ' . $path);
}

it('generates valid PHP for the whole API', function () {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();

    foreach (fullApi() as $path => $code) {
        expect($parser->parse($code))->not->toBeNull($path)
            ->and($code)->toContain(PhpFile::MARKER);
    }

    expect(fullApi())->toHaveCount(883);
});

it('generates one resource, model, factory and contract test file per class', function () {
    $count = fn(string $prefix): int => count(array_filter(array_keys(fullApi()), fn(string $path): bool => str_starts_with($path, $prefix)));

    expect($count('src/Resources/Reports/'))->toBe(27)
        ->and($count('src/Resources/Public/'))->toBe(4)
        ->and($count('src/Data/Models/'))->toBe(137)
        ->and($count('src/Testing/Factories/'))->toBe(137)
        ->and($count('src/Data/Input/'))->toBe(216)
        ->and($count('tests/Contract/Generated/'))->toBe(140);
});

it('generates the public endpoints without the organization header', function () {
    expect(generated('src/Resources/Public/PublicArtifactResource.php'))
        ->toContain('namespace Axyr\\Productive\\Resources\\Public;')
        ->toContain('protected const bool REQUIRES_ORGANIZATION = false;')
        ->toContain('public function attachmentsAuth(string $uuid, int|string $id): Response')
        ->toContain("return \$this->raw(Method::Get, \$this->path(\$uuid, 'attachments', \$id, 'auth'), 'public.artifacts.attachments_auth');")
        ->and(generated('src/Resources/Public/PublicResources.php'))
        ->toContain('public function artifacts(): PublicArtifactResource')
        ->and(generated('src/Concerns/ProvidesResources.php'))
        ->toContain('public function public(): PublicResources')
        ->toContain('public function reports(): Reports')
        ->and(generated('src/ProductiveFacade.php'))
        ->toContain('@method static \\Axyr\\Productive\\Resources\\Public\\PublicResources public()');
});

it('generates raw, optional, plain and untyped body methods', function () {
    expect(generated('src/Resources/ProposalResource.php'))
        ->toContain('public function signedPdf(int|string $id): Response')
        ->toContain('public function create(array $data): Proposal')
        ->toContain("return \$this->write(Proposal::class, Method::Post, \$this->path(), 'proposals.create', \$data);")
        ->toContain('public function update(int|string $id, array $data): Proposal')
        ->and(generated('src/Resources/SessionResource.php'))
        ->toContain("return \$this->raw(Method::Post, \$this->path('machine'), 'sessions.machine', \$data === [] ? null : \$data);")
        ->and(generated('src/Resources/PageResource.php'))
        ->toContain('public function appendMarkdown(int|string $id, AppendMarkdownPageData|array $data): Page')
        ->toContain("return \$this->writePlain(Page::class, Method::Patch, \$this->path(\$id, 'append_markdown'), 'pages.append_markdown', \$data);")
        ->and(generated('src/Resources/ExpenseResource.php'))
        ->toContain('public function copy(CopyExpenseData|array $data): ?Expense')
        ->toContain("return \$this->writeOptional(Expense::class, Method::Post, \$this->path('copy'), 'expenses.copy', \$data);")
        ->and(generated('src/Resources/BoardResource.php'))
        ->toContain('public function copy(array $data = []): ?Board')
        ->toContain("return \$this->writeOptional(Board::class, Method::Post, \$this->path('copy'), 'boards.copy', \$data === [] ? null : \$data);")
        ->and(generated('src/Resources/DealResource.php'))
        ->toContain("return \$this->writeAsBulk(Deal::class, Method::Post, \$this->path('copy'), 'deals.copy', \$data);")
        ->and(generated('tests/Contract/Generated/DealResourceTest.php'))
        ->toContain("RequestSchema::assertValid('deals-copy-copy'")
        ->toContain("'application/vnd.api+json; ext=bulk'")
        ->and(generated('src/Resources/InvoiceResource.php'))
        ->toContain("return \$this->fetchOne(Invoice::class, \$this->path(\$id, 'preview'), 'invoices.preview');");
});

it('generates the report resources with page pagination and their rate limit', function () {
    expect(generated('src/Resources/Reports/BudgetReportResource.php'))
        ->toContain('protected const bool SUPPORTS_CURSOR = false;')
        ->toContain('return [RateLimit::reports()];')
        ->not->toContain('REQUIRES_ORGANIZATION')
        ->and(generated('src/Resources/Reports/Reports.php'))
        ->toContain('public function budgetReports(): BudgetReportResource');
});

it('types relationships to generated models and leaves the rest generic', function () {
    expect(generated('src/Data/Models/Task.php'))
        ->toContain('public function assignee(): ?Person')
        ->toContain("return \$this->hasMany('attachments', Attachment::class);")
        ->toContain('public function templateObject(): ?Model')
        ->and(generated('src/Data/ModelMap.php'))
        ->toContain("'time_reports' => TimeReport::class,")
        ->toContain('Task::TYPE => Task::class,');
});

it('renames attributes that would shadow model members', function () {
    expect(generated('src/Data/Models/ContactEntry.php'))
        ->toContain('public ?string $typeValue;')
        ->toContain("\$this->typeValue = \$attributes->string('type');");
});

it('generates contract tests for every special case', function () {
    expect(generated('tests/Contract/Generated/PublicArtifactResourceTest.php'))
        ->toContain("Productive::public()->artifacts()->attachmentsAuth('uuid-1', '1')")
        ->toContain("urldecode(\$request->url()) === apiUrl('public/artifacts/uuid-1/attachments/1/auth')")
        ->toContain('expect($result)->toBeInstanceOf(Response::class);')
        ->and(generated('tests/Contract/Generated/PageResourceTest.php'))
        ->toContain("RequestSchema::assertValid('pages-proxy-append-markdown', json_decode(\$request->body(), true));")
        ->toContain("json_decode(\$request->body(), true) === ['markdown' => 'Example']")
        ->and(generated('tests/Contract/Generated/ProposalResourceTest.php'))
        ->toContain("->create(['name' => 'Example'])")
        ->and(generated('tests/Contract/Generated/LineItemResourceTest.php'))
        ->toContain("'application/vnd.api+json; ext=bulk'");
});

it('matches the committed manifest of the whole generated API', function () {
    $committed = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/generator/full-api.sha1.json'), true);
    $current = array_map(sha1(...), generateFullApi());
    $changed = array_keys(array_diff_assoc($current, $committed) + array_diff_key($committed, $current));

    expect($changed)->toBe([], 'The generator output changed: run `composer generate:manifest` and review the diff.');
})->group('drift');
