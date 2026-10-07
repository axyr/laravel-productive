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
     * @param  array<string, mixed>  $body
     */
    public static function assertValid(string $operationId, array $body): void
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
    }

    /**
     * Validate each attribute on its own, for update endpoints: Productive shares one request
     * schema between create and update, so its "required" list does not apply to a PATCH.
     * Explicit nulls (clearing a field) are not described by the spec and are skipped.
     *
     * @param  array<string, mixed>  $body
     */
    public static function assertValidAttributes(string $operationId, array $body): void
    {
        $base = SpecExamples::requestSchemaPointer($operationId) . '/properties/data/properties/attributes/properties/';

        foreach ($body['data']['attributes'] ?? [] as $name => $value) {
            if ($value === null) {
                continue;
            }

            $result = self::validator()->validate(json_decode(json_encode($value, JSON_THROW_ON_ERROR)), self::SPEC_ID . $base . $name);

            Assert::assertTrue($result->isValid(), sprintf('The %s attribute "%s" does not match the spec.', $operationId, $name));
        }
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
