<?php

declare(strict_types=1);

namespace Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Assert;

/**
 * Validates request bodies against the request body schemas in the vendored OpenAPI spec.
 */
final class RequestSchema
{
    private const SPEC_ID = 'https://developer.productive.io/openapi.json';

    private static ?Validator $validator = null;

    /**
     * The body must match the spec's schema. A JSON:API body (a type is given) must also carry the
     * resource object(s) of that type in "data", and no attribute the schema does not list: the
     * spec allows unknown keys, so a misspelled or misplaced field would otherwise pass.
     *
     * @param  array<string, mixed>  $body
     */
    public static function assertValid(string $operationId, array $body, ?string $type = null): void
    {
        $result = self::validator()->validate(
            json_decode(json_encode($body, JSON_THROW_ON_ERROR)),
            self::SPEC_ID . SpecExamples::requestSchemaPointer($operationId),
        );

        Assert::assertTrue(
            $result->isValid(),
            sprintf(
                "The %s request body does not match the spec:\n%s\n%s",
                $operationId,
                json_encode($result->error() !== null ? (new ErrorFormatter())->format($result->error()) : [], JSON_PRETTY_PRINT),
                json_encode($body, JSON_PRETTY_PRINT),
            ),
        );

        self::assertKnownNames($operationId, $type === null ? array_keys($body) : self::attributeNames($operationId, $body, $type));
    }

    /**
     * Validate each attribute on its own, for update endpoints: Productive shares one request
     * schema between create and update, so its "required" list does not apply to a PATCH.
     * Explicit nulls (clearing a field) are not described by the spec and are skipped.
     *
     * @param  array<string, mixed>  $body
     */
    public static function assertValidAttributes(string $operationId, array $body, string $type): void
    {
        $base = SpecExamples::requestSchemaPointer($operationId) . '/properties/data/properties/attributes/properties/';
        $names = self::attributeNames($operationId, $body, $type);

        self::assertKnownNames($operationId, $names);

        foreach ($body['data']['attributes'] ?? [] as $name => $value) {
            if ($value === null) {
                continue;
            }

            $result = self::validator()->validate(json_decode(json_encode($value, JSON_THROW_ON_ERROR)), self::SPEC_ID . $base . $name);

            Assert::assertTrue($result->isValid(), sprintf('The %s attribute "%s" does not match the spec.', $operationId, $name));
        }
    }

    /**
     * The attribute names of every resource object in "data", after checking each has the type.
     *
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    private static function attributeNames(string $operationId, array $body, string $type): array
    {
        $data = $body['data'] ?? null;

        Assert::assertIsArray($data, sprintf('The %s request body has no "data" resource object.', $operationId));

        $names = [];

        foreach (array_is_list($data) ? $data : [$data] as $resource) {
            Assert::assertSame($type, $resource['type'] ?? null, sprintf('The %s request sends a resource object that is not of type "%s".', $operationId, $type));
            $names = [...$names, ...array_keys($resource['attributes'] ?? [])];
        }

        return array_map(strval(...), $names);
    }

    /**
     * @param  list<int|string>  $names
     */
    private static function assertKnownNames(string $operationId, array $names): void
    {
        $unknown = array_values(array_diff(array_map(strval(...), $names), SpecExamples::requestAttributeNames($operationId)));

        Assert::assertSame([], $unknown, sprintf('The %s request sends attributes the spec does not list: %s.', $operationId, implode(', ', $unknown)));
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->setMaxErrors(5);
            self::$validator->resolver()?->registerRaw((string) file_get_contents(SpecExamples::path()), self::SPEC_ID);
        }

        return self::$validator;
    }
}
