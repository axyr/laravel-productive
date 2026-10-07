<?php

declare(strict_types=1);

namespace Axyr\Productive\Testing;

use Axyr\Productive\Data\Model;
use Axyr\Productive\Http\Response;
use Axyr\Productive\JsonApi\Relationship;

/**
 * Builds responses for the testing fake.
 */
final class FakeResponse
{
    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, string>  $headers
     */
    public static function json(array $document, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/vnd.api+json', ...$headers], json_encode($document, JSON_THROW_ON_ERROR));
    }

    /**
     * A single resource document.
     *
     * @param  array<string, mixed>|Model  $resource  A resource object array (see Factory::resource()) or a model.
     * @param  list<array<string, mixed>|Model>  $included
     */
    public static function resource(array|Model $resource, array $included = [], int $status = 200): Response
    {
        return self::json(['data' => self::resourceObject($resource), 'included' => self::resourceObjects($included)], $status);
    }

    /**
     * A collection document with page-based meta.
     *
     * @param  list<array<string, mixed>|Model>  $resources
     * @param  list<array<string, mixed>|Model>  $included
     * @param  array<string, mixed>  $meta  Merged over the generated pagination meta.
     * @param  array<string, mixed>  $links
     */
    public static function collection(array $resources = [], array $included = [], array $meta = [], array $links = []): Response
    {
        $count = count($resources);

        return self::json([
            'data' => self::resourceObjects($resources),
            'included' => self::resourceObjects($included),
            'meta' => [
                'current_page' => 1,
                'total_pages' => $count === 0 ? 0 : 1,
                'total_count' => $count,
                'page_size' => max($count, 30),
                'max_page_size' => 200,
                ...$meta,
            ],
            'links' => $links,
        ]);
    }

    public static function noContent(): Response
    {
        return new Response(204);
    }

    public static function binary(string $contents, string $contentType = 'application/pdf'): Response
    {
        return new Response(200, ['Content-Type' => $contentType], $contents);
    }

    /**
     * A JSON:API error response, e.g. error(404, 'Record Not Found').
     */
    public static function error(int $status, string $title, ?string $detail = null, ?string $code = null): Response
    {
        $error = array_filter(
            ['status' => $status, 'title' => $title, 'detail' => $detail, 'code' => $code],
            fn(int|string|null $value): bool => $value !== null,
        );

        return self::json(['errors' => [$error]], $status);
    }

    /**
     * A 422 with one error per attribute, e.g. validation(['title' => "can't be blank"]).
     *
     * @param  array<string, string>  $messages
     */
    public static function validation(array $messages): Response
    {
        $errors = [];

        foreach ($messages as $attribute => $message) {
            $errors[] = [
                'status' => 422,
                'title' => 'Invalid Attribute',
                'detail' => $message,
                'source' => ['pointer' => '/data/attributes/' . $attribute],
            ];
        }

        return self::json(['errors' => $errors], 422);
    }

    public static function rateLimited(int $retryAfter = 10): Response
    {
        return self::json(
            ['errors' => [['status' => 429, 'title' => 'Too many requests', 'detail' => 'Rate limit exceeded', 'limit' => 100, 'period' => 10]]],
            429,
            ['X-RateLimit-Reset' => (string) $retryAfter],
        );
    }

    /**
     * Responses returned one after another for consecutive matching requests.
     */
    public static function sequence(Response ...$responses): ResponseSequence
    {
        return new ResponseSequence(...$responses);
    }

    /**
     * @param  array<string, mixed>|Model  $resource
     * @return array<string, mixed>
     */
    private static function resourceObject(array|Model $resource): array
    {
        if (! $resource instanceof Model) {
            return $resource;
        }

        $object = ['type' => $resource->type, 'id' => $resource->id, 'attributes' => $resource->attributes()];
        $relationships = array_map(self::relationshipObject(...), $resource->resource()->relationships);

        return $relationships === [] ? $object : [...$object, 'relationships' => $relationships];
    }

    /**
     * @return array<string, mixed>
     */
    private static function relationshipObject(Relationship $relationship): array
    {
        if (! $relationship->hasData) {
            return ['meta' => $relationship->meta];
        }

        $identifiers = $relationship->identifiers();

        return ['data' => $relationship->isToMany() ? $identifiers : ($identifiers[0] ?? null)];
    }

    /**
     * @param  list<array<string, mixed>|Model>  $resources
     * @return list<array<string, mixed>>
     */
    private static function resourceObjects(array $resources): array
    {
        return array_map(self::resourceObject(...), $resources);
    }
}
