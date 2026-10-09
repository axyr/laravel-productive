<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Emit\CodeGenerator;
use Axyr\Productive\Generator\Emit\EnumEmitter;
use Axyr\Productive\Generator\Emit\GeneratedFiles;
use Axyr\Productive\Generator\Emit\Literal;
use Axyr\Productive\Generator\Emit\PhpFile;
use Axyr\Productive\Generator\Emit\ResourceMethod;
use Axyr\Productive\Generator\Emit\Samples;
use Axyr\Productive\Generator\Emit\Types;
use Axyr\Productive\Generator\Ir\Api;
use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Input;
use Axyr\Productive\Generator\Ir\Operation;
use Axyr\Productive\Generator\Ir\OperationKind;
use Axyr\Productive\Generator\Ir\Resource;
use Axyr\Productive\Generator\Ir\ResponseKind;

it('exports PHP literals', function (mixed $value, string $literal) {
    expect(Literal::export($value))->toBe($literal);
})->with([
    [null, 'null'],
    [true, 'true'],
    [false, 'false'],
    [42, '42'],
    [1.5, '1.5'],
    [2.0, '2.0'],
    ["It's a \\ path", "'It\\'s a \\\\ path'"],
    [[1, 'a'], "[1, 'a']"],
    [['k' => ['nested' => null], 3 => true], "['k' => ['nested' => null], 3 => true]"],
    [[], '[]'],
]);

it('refuses values that have no literal', function () {
    Literal::export(new stdClass());
})->throws(InvalidArgumentException::class, 'Cannot export a stdClass as a PHP literal.');

it('maps attribute types to model and input types', function (AttributeType $type, string $name, string $model, string $input) {
    $attribute = new Attribute($name, $name, $type);

    expect(Types::model($attribute)['type'])->toBe($model)
        ->and(Types::input($attribute)['type'])->toBe($input);
})->with([
    [AttributeType::String, 'title', '?string', 'string'],
    [AttributeType::Time, 'due_time', '?string', 'DateTimeInterface|string'],
    [AttributeType::Int, 'count', '?int', 'int'],
    [AttributeType::Int, 'project_id', '?int', 'int|string'],
    [AttributeType::Float, 'rate', '?float', 'float|int'],
    [AttributeType::Bool, 'private', '?bool', 'bool'],
    [AttributeType::Date, 'due_date', '?DateTimeImmutable', 'DateTimeInterface|string'],
    [AttributeType::DateTime, 'started_at', '?DateTimeImmutable', 'DateTimeInterface|string'],
    [AttributeType::Object, 'custom_fields', '?array', 'array'],
    [AttributeType::List, 'repeat_on_weekday', '?array', 'array'],
    [AttributeType::Mixed, 'cost', 'mixed', 'mixed'],
    [AttributeType::Mixed, 'subscriber_ids', 'mixed', 'array'],
    [AttributeType::Mixed, 'tag_list', 'mixed', 'array'],
]);

it('documents array-shaped types', function () {
    expect(Types::model(new Attribute('a', 'a', AttributeType::Object))['doc'])->toBe('array<array-key, mixed>|null')
        ->and(Types::model(new Attribute('a', 'a', AttributeType::List))['doc'])->toBe('list<mixed>|null')
        ->and(Types::input(new Attribute('a', 'a', AttributeType::Object))['doc'])->toBe('array<string, mixed>')
        ->and(Types::input(new Attribute('a', 'a', AttributeType::List))['doc'])->toBe('list<mixed>')
        ->and(Types::input(new Attribute('subscriber_ids', 's', AttributeType::Mixed))['doc'])->toBe('list<int|string>')
        ->and(Types::input(new Attribute('tag_list', 't', AttributeType::Mixed))['doc'])->toBe('list<string>')
        ->and(Types::input(new Attribute('due_date', 'd', AttributeType::Date))['format'])->toBe('date')
        ->and(Types::input(new Attribute('due_time', 'd', AttributeType::Time))['format'])->toBe('time');
});

it('picks sample values for contract tests', function () {
    $input = new Input('X', 'x', [
        new Attribute('title', 'title', AttributeType::String, required: true),
        new Attribute('status', 'status', AttributeType::Int, required: true, enum: [3, 4]),
        new Attribute('note', 'note', AttributeType::String),
    ]);

    expect(Samples::attributes($input, OperationKind::Create))->toBe(['title' => 'Example', 'status' => 3])
        ->and(Samples::attributes($input, OperationKind::Update))->toBe(['title' => 'Example'])
        ->and(Samples::attributes($input, OperationKind::UpdateBulk))->toBe(['title' => 'Example'])
        ->and(Samples::attributes(new Input('Y', 'y', [new Attribute('note', 'note', AttributeType::String)]), OperationKind::Action))->toBe(['note' => 'Example'])
        ->and(Samples::attributes(null, OperationKind::Create))->toBe(['name' => 'Example']);
});

it('picks a sample value per type', function (AttributeType $type, mixed $value) {
    expect(Samples::value(new Attribute('a', 'a', $type)))->toBe($value);
})->with([
    [AttributeType::Int, 1],
    [AttributeType::Float, 1.5],
    [AttributeType::Bool, true],
    [AttributeType::Date, '2026-01-15'],
    [AttributeType::DateTime, '2026-01-15T09:00:00+00:00'],
    [AttributeType::Time, '09:00'],
    [AttributeType::Object, ['1' => 'value']],
    [AttributeType::List, ['value']],
    [AttributeType::String, 'Example'],
    [AttributeType::Mixed, 'Example'],
]);

it('renders files and docblocks', function () {
    expect(PhpFile::docblock([]))->toBe([])
        ->and(PhpFile::docblock(['Line.', '', 'More.'], '    '))->toBe(['    /**', '     * Line.', '     *', '     * More.', '     */'])
        ->and(PhpFile::render('App', ['App\\Same', 'Other\\B', 'DateTimeImmutable', 'Other\\A', 'Other\\B'], ['final class X {}']))
        ->toBe("<?php\n\ndeclare(strict_types=1);\n\n" . PhpFile::MARKER . "\n\nnamespace App;\n\nuse DateTimeImmutable;\nuse Other\\A;\nuse Other\\B;\n\nfinal class X {}\n")
        ->and(PhpFile::render('App', [], ['final class X {}']))->toBe("<?php\n\ndeclare(strict_types=1);\n\n" . PhpFile::MARKER . "\n\nnamespace App;\n\nfinal class X {}\n");
});

it('refuses enum values that collide on a case name', function () {
    EnumEmitter::sort('XSort', 'Sorts.', ['due_date', 'due-date']);
})->throws(RuntimeException::class, 'XSort: "due_date" and "due-date" both become case DueDate.');

it('refuses actions that return a collection', function () {
    $operation = new Operation('tasks.list_all', 'listAll', OperationKind::Action, 'POST', 'tasks/list_all', [], ResponseKind::Collection, false, true, 'x');

    (new ResourceMethod(new Resource('tasks', 'TaskResource', 'Axyr\\Productive\\Resources', 'tasks', 'Task', [$operation]), $operation, 'Task'))->lines();
})->throws(RuntimeException::class, 'tasks.list_all: actions returning a collection are not supported yet.');

it('refuses unknown resources in the config', function () {
    (new CodeGenerator(new Api([], []), ['nope']))->files();
})->throws(RuntimeException::class, 'generator/config/resources.php: "nope" is not a resource in the spec.');

it('writes files, removes stale generated files and reports drift', function () {
    $root = sys_get_temp_dir() . '/generated-files-' . uniqid();
    mkdir($root . '/src/Old', 0o777, true);
    file_put_contents($root . '/src/Old/Stale.php', "<?php\n" . PhpFile::MARKER . "\n");
    file_put_contents($root . '/src/Old/Handwritten.php', "<?php\n");
    file_put_contents($root . '/src/Old/MentionsMarker.php', "<?php\n\n\n\n\n\n// Example of the marker:\n" . PhpFile::MARKER . "\n");
    file_put_contents($root . '/src/Old/notes.txt', PhpFile::MARKER);
    $formatted = [];
    $format = function (string $directory, array $paths) use (&$formatted): void {
        $formatted[] = [basename($directory) === basename(sys_get_temp_dir()) ? 'tmp' : 'dir', $paths];
    };

    $files = new GeneratedFiles($root, ['src/New/A.php' => "<?php // a\n", 'tests/Contract/Generated/BTest.php' => "<?php // b\n"], $format);

    expect($files->check())->toBe([
        'src/New/A.php is missing.',
        'tests/Contract/Generated/BTest.php is missing.',
        'src/Old/Stale.php is generated but no longer produced.',
    ]);

    $files->write();

    expect(file_get_contents($root . '/src/New/A.php'))->toBe("<?php // a\n")
        ->and(is_file($root . '/src/Old/Stale.php'))->toBeFalse()
        ->and(is_file($root . '/src/Old/Handwritten.php'))->toBeTrue()
        ->and(is_file($root . '/src/Old/MentionsMarker.php'))->toBeTrue()
        ->and(is_file($root . '/src/Old/notes.txt'))->toBeTrue()
        ->and($files->check())->toBe([])
        ->and($formatted[1][1])->toBe(['src/New/A.php', 'tests/Contract/Generated/BTest.php']);

    file_put_contents($root . '/src/New/A.php', "<?php // changed\n");

    expect($files->check())->toBe(['src/New/A.php differs from the generator output.']);
});

it('formats with Pint by default and reports a failure', function () {
    $root = sys_get_temp_dir() . '/generated-files-' . uniqid();
    mkdir($root);

    (new GeneratedFiles($root, ['Broken.php' => "<?php\nclass {"]))->write();
})->throws(RuntimeException::class, 'Pint failed')->group('drift');

it('sorts accessors and groups however the config lists resources', function () {
    $resource = fn(string $path, string $class): Resource => new Resource($path, $class, str_starts_with($path, 'reports/') ? 'Axyr\\Productive\\Resources\\Reports' : 'Axyr\\Productive\\Resources', $path, 'X', []);
    $files = Axyr\Productive\Generator\Emit\ClientEmitter::emit([
        $resource('time_entries', 'TimeEntryResource'),
        $resource('reports/time_reports', 'TimeReportResource'),
        $resource('tasks', 'TaskResource'),
        $resource('reports/budget_reports', 'BudgetReportResource'),
    ]);

    expect(strpos($files['src/Concerns/ProvidesResources.php'], 'function reports()'))->toBeLessThan(strpos($files['src/Concerns/ProvidesResources.php'], 'function tasks()'))
        ->and(strpos($files['src/Concerns/ProvidesResources.php'], 'function tasks()'))->toBeLessThan(strpos($files['src/Concerns/ProvidesResources.php'], 'function timeEntries()'))
        ->and(strpos($files['src/Resources/Reports/Reports.php'], 'function budgetReports()'))->toBeLessThan(strpos($files['src/Resources/Reports/Reports.php'], 'function timeReports()'))
        ->and($files)->not->toHaveKey('src/Resources/Public/PublicResources.php')
        ->and($files['src/ProductiveFacade.php'])->not->toContain('public()')
        ->and(Axyr\Productive\Generator\Emit\ClientEmitter::accessor($resource('time_entries', 'TimeEntryResource')))->toBe('timeEntries')
        ->and(Axyr\Productive\Generator\Emit\ClientEmitter::accessor($resource('reports/time_reports', 'TimeReportResource')))->toBe('reports()->timeReports');
});

it('writes @param lines without trailing space when there is no description', function () {
    $code = Axyr\Productive\Generator\Emit\InputEmitter::emit(new Input('XData', 'x', [new Attribute('tag_list', 'tagList', AttributeType::Mixed)]), 'Summary.');

    expect($code)->toContain("     * @param  list<string>|Undefined|null  \$tagList\n");
});

it('refuses a resource without a model', function () {
    (new CodeGenerator(new Api([new Resource('webhooks', 'WebhookResource', 'Axyr\\Productive\\Resources', 'webhooks', null, [])], []), ['webhooks']))->files();
})->throws(RuntimeException::class, 'webhooks has no model to generate.');

it('names actions and member parameters', function () {
    $operation = fn(string $key, string $path): Operation => new Operation($key, 'x', OperationKind::Action, 'PATCH', $path, [], ResponseKind::NoContent, false, true, null);
    $resource = new Resource('public/artifacts', 'PublicArtifactResource', 'Axyr\\Productive\\Resources\\Public', 'artifacts', 'Artifact', []);

    expect($operation('reports.time_reports.index', 'x')->actionName())->toBe('index')
        ->and(ResourceMethod::memberParameter($resource, $operation('public.artifacts.attachments_auth', 'public/artifacts/{uuid}/attachments/{id}/auth')))->toBe('uuid')
        ->and(ResourceMethod::memberParameter($resource, $operation('public.artifacts.copy', 'public/artifacts/copy')))->toBeNull()
        ->and(ResourceMethod::memberParameter($resource, $operation('public.artifacts.index', 'public/artifacts')))->toBeNull();
});

it('cleans up its scratch directory and reports each problem once', function () {
    $root = sys_get_temp_dir() . '/generated-files-' . uniqid();
    $scratch = sys_get_temp_dir() . '/generated-scratch-' . uniqid();
    mkdir($root . '/src', 0o777, true);
    file_put_contents($root . '/src/A.php', "<?php // a\n");
    file_put_contents($root . '/src/B.php', "<?php // old\n");

    $files = new GeneratedFiles($root, ['src/A.php' => "<?php // a\n", 'src/B.php' => "<?php // b\n"], fn() => null, $scratch);

    expect($files->check())->toBe(['src/B.php differs from the generator output.'])
        ->and(is_dir($scratch))->toBeFalse();
});

it('formats only the generated files', function () {
    $root = sys_get_temp_dir() . '/generated-files-' . uniqid();
    mkdir($root . '/src', 0o777, true);
    $untouched = "<?php\nclass  Spaced  {}\n";
    file_put_contents($root . '/src/Other.php', $untouched);

    (new GeneratedFiles($root, ['src/A.php' => "<?php\nclass  A  {}\n"]))->write();

    expect(file_get_contents($root . '/src/Other.php'))->toBe($untouched)
        ->and(file_get_contents($root . '/src/A.php'))->not->toContain('class  A');
})->group('drift');

it('documents the item type of input lists; model lists stay lenient', function (?AttributeType $items, string $input) {
    $attribute = new Attribute('a', 'a', AttributeType::List, items: $items);

    expect(Types::input($attribute)['doc'])->toBe($input)
        ->and(Types::model($attribute)['doc'])->toBe('list<mixed>|null');
})->with([
    [AttributeType::Int, 'list<int>'],
    [AttributeType::Float, 'list<float>'],
    [AttributeType::Bool, 'list<bool>'],
    [AttributeType::String, 'list<string>'],
    [null, 'list<mixed>'],
]);

it('samples list items and map values of the documented type', function (?AttributeType $items, mixed $value) {
    expect(Samples::value(new Attribute('a', 'a', AttributeType::List, items: $items)))->toBe([$value])
        ->and(Samples::value(new Attribute('a', 'a', AttributeType::Object, items: $items)))->toBe(['1' => $value])
        ->and(Types::input(new Attribute('a', 'a', AttributeType::Object, items: $items))['doc'])->toBe('array<string, ' . ($items === null ? 'mixed' : get_debug_type($value)) . '>');
})->with([
    [AttributeType::Int, 1],
    [AttributeType::Float, 1.5],
    [AttributeType::Bool, true],
    [AttributeType::String, 'value'],
    [null, 'value'],
]);

it('refuses a bulk-document action without an optional resource response', function () {
    $operation = new Operation('deals.copy', 'copy', OperationKind::Action, 'POST', 'deals/copy', [], ResponseKind::Resource, false, true, 'x', new Input('CopyDealData', 'deal_bulk_copy', [], bulk: true), body: Axyr\Productive\Generator\Ir\BodyKind::BulkItem);

    (new ResourceMethod(new Resource('deals', 'DealResource', 'Axyr\\Productive\\Resources', 'deals', 'Deal', [$operation]), $operation, 'Deal'))->lines();
})->throws(RuntimeException::class, 'deals.copy: a bulk-document action must document an optional resource response.');

it('lists the imports of a resource method as a list', function () {
    $operation = new Operation('tasks.show', 'find', OperationKind::Show, 'GET', 'tasks/{id}', ['id'], ResponseKind::Resource, false, true, 'tasks-show');
    $method = new ResourceMethod(new Resource('tasks', 'TaskResource', 'Axyr\\Productive\\Resources', 'tasks', 'Task', [$operation]), $operation, 'Task');

    expect($method->imports())->toBe(['Axyr\\Productive\\Query\\Query']);
});
