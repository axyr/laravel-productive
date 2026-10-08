<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Spec;

it('reads a spec file', function () {
    $path = tempnam(sys_get_temp_dir(), 'spec');
    file_put_contents($path, json_encode(['paths' => ['/api/v2/tasks' => ['get' => ['operationId' => 'tasks-index']]]]));

    expect(Spec::fromFile($path)->operations())->toBe([['tasks', 'GET', ['operationId' => 'tasks-index']]]);

    unlink($path);
});

it('refuses a missing file and invalid JSON', function () {
    $path = tempnam(sys_get_temp_dir(), 'spec');
    file_put_contents($path, '{');

    expect(fn() => Spec::fromFile('/does/not/exist.json'))->toThrow(RuntimeException::class, 'Cannot read the OpenAPI spec at /does/not/exist.json.')
        ->and(fn() => Spec::fromFile(sys_get_temp_dir()))->toThrow(RuntimeException::class, 'Cannot read the OpenAPI spec')
        ->and(fn() => Spec::fromFile($path))->toThrow(JsonException::class);

    unlink($path);
});

it('lists operations with their method and path, skipping other path items', function () {
    $spec = new Spec(['paths' => [
        '/api/v2/tasks/{id}' => ['parameters' => [], 'get' => ['a' => 1], 'patch' => ['b' => 2], 'delete' => 'not an operation'],
        '/other/path' => ['put' => ['c' => 3], 'head' => ['d' => 4]],
    ]]);

    expect($spec->operations())->toBe([
        ['tasks/{id}', 'GET', ['a' => 1]],
        ['tasks/{id}', 'PATCH', ['b' => 2]],
        ['tasks/{id}', 'DELETE', []],
        ['other/path', 'PUT', ['c' => 3]],
    ]);
});

it('resolves chained references, including escaped segments', function () {
    $spec = new Spec(['components' => [
        'schemas' => ['a' => ['$ref' => '#/components/schemas/b'], 'b' => ['type' => 'string'], 'c/d' => ['x' => 1], 'e~f' => ['y' => 2]],
    ]]);

    expect($spec->resolve(['$ref' => '#/components/schemas/a']))->toBe(['type' => 'string'])
        ->and($spec->resolve(['type' => 'integer']))->toBe(['type' => 'integer'])
        ->and($spec->resolve('scalar'))->toBe([])
        ->and($spec->pointer('#/components/schemas/c~1d'))->toBe(['x' => 1])
        ->and($spec->pointer('#/components/schemas/e~0f'))->toBe(['y' => 2]);
});

it('rejects remote and unresolvable references', function () {
    $spec = new Spec(['components' => ['schemas' => ['a' => 'leaf']]]);

    expect(fn() => $spec->pointer('other.json#/a'))->toThrow(RuntimeException::class, 'Only local references are supported, "other.json#/a" given.')
        ->and(fn() => $spec->pointer('#/components/missing'))->toThrow(RuntimeException::class, 'Unresolvable reference "#/components/missing".')
        ->and(fn() => $spec->pointer('#/components/schemas/a/deeper'))->toThrow(RuntimeException::class, 'Unresolvable reference');
});

it('narrows mixed values', function () {
    expect(Spec::map(['a' => 1]))->toBe(['a' => 1])
        ->and(Spec::map('x'))->toBe([])
        ->and(Spec::string('x'))->toBe('x')
        ->and(Spec::string(1))->toBe('');
});

it('accepts lists of strings only', function () {
    expect(Spec::strings(['a' => 'x', 'b' => 'y'], 'Tags'))->toBe(['x', 'y'])
        ->and(Spec::strings('not a list', 'Tags'))->toBe([])
        ->and(fn() => Spec::strings(['x', null], 'Tags'))->toThrow(RuntimeException::class, 'Tags must only contain strings, null found.');
});
