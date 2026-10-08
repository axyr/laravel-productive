<?php

declare(strict_types=1);

use Axyr\Productive\Pagination\ModelCollection;
use Axyr\Productive\Testing\Factories\TaskFactory;

it('carries the page meta and links', function () {
    $collection = ModelCollection::fromPage(TaskFactory::new()->count(2)->makeMany(), ['total_count' => 42], ['next' => 'x']);

    expect($collection)->toHaveCount(2)
        ->and($collection->meta())->toBe(['total_count' => 42])
        ->and($collection->links())->toBe(['next' => 'x'])
        ->and($collection->totalCount())->toBe(42);
});

it('reads totals sent as numeric strings', function () {
    $collection = ModelCollection::fromPage([], ['total_count' => '120', 'total_pages' => '4']);

    expect($collection->totalCount())->toBe(120)
        ->and($collection->metaInt('total_pages'))->toBe(4);
});

it('has no total without page meta', function () {
    $collection = ModelCollection::fromPage([], ['total_count' => 'many']);

    expect($collection->totalCount())->toBeNull()
        ->and((new ModelCollection())->meta())->toBe([])
        ->and((new ModelCollection())->links())->toBe([]);
});
