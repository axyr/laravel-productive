<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use Axyr\Productive\Generator\Ir\BodyKind;
use Axyr\Productive\Http\Method;
use Axyr\Productive\JsonApi\DocumentBuilder;
use Axyr\Productive\Query\QuerySerializer;

/**
 * The body of a write run's request, as the SDK would build it.
 */
final class WriteBody
{
    /**
     * The JSON:API document of the request; an update carries the record's ID, a create none. A
     * plain JSON body, such as {"markdown": "…"}, is the attributes themselves. A GET has none: see query().
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>|null
     */
    public static function build(WriteStep $step, Method $method, ?string $id, ?array $attributes): ?array
    {
        if ($method === Method::Get) {
            return null;
        }

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
     * The query string of a GET action, whose attributes are its query parameters.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function query(Method $method, ?array $attributes): string
    {
        /** @var array<string, scalar|list<scalar>> $parameters */
        $parameters = $method === Method::Get ? $attributes ?? [] : [];

        return QuerySerializer::parameters($parameters);
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
