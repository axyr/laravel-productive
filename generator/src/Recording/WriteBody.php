<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Http\Method;
use Axyr\Productive\JsonApi\DocumentBuilder;

/**
 * The body of a write run's request, as the SDK would build it.
 */
final class WriteBody
{
    /**
     * The JSON:API document of the request; an update carries the record's ID, a create none. A
     * plain JSON body, such as {"markdown": "…"}, is the attributes themselves.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>|null
     */
    public static function build(WriteStep $step, Method $method, ?string $id, ?array $attributes): ?array
    {
        $id = $method === Method::Post ? null : $id;

        return $step->operation->bulk ? self::bulkBody($step->type, $id, $attributes) : self::singleBody($step, $id, $attributes);
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>|null
     */
    private static function singleBody(WriteStep $step, ?string $id, ?array $attributes): ?array
    {
        return $attributes === null || $step->operation->body === BodyKind::Plain ? $attributes : DocumentBuilder::resource($step->type, $attributes, $id);
    }

    /**
     * A bulk document of one item: attributes for a create, the ID and attributes for an update,
     * the identifier alone for an action or a delete.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>
     */
    private static function bulkBody(string $type, ?string $id, ?array $attributes): array
    {
        if ($attributes === null) {
            return DocumentBuilder::identifiers($type, $id === null ? [] : [$id]);
        }

        return DocumentBuilder::bulk($type, [$id === null ? ['attributes' => $attributes] : ['id' => $id, 'attributes' => $attributes]]);
    }
}
