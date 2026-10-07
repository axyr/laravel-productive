<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

use Axyr\Productive\Http\Request;
use Axyr\Productive\Http\Response;
use Axyr\Productive\JsonApi\ErrorObject;
use Throwable;

/**
 * Productive answered with an error status. Subclasses exist for every status the API documents.
 */
class ApiException extends ProductiveException
{
    /**
     * @param  list<ErrorObject>  $errors
     */
    final public function __construct(
        string $message,
        public readonly Request $request,
        public readonly Response $response,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response->status, $previous);
    }

    public static function fromResponse(Request $request, Response $response): self
    {
        $errors = self::parseErrors($response);

        $class = match (true) {
            $response->status === 400 => BadRequestException::class,
            $response->status === 401 => AuthenticationException::class,
            $response->status === 402 => PaymentRequiredException::class,
            $response->status === 403 => AuthorizationException::class,
            $response->status === 404 => NotFoundException::class,
            $response->status === 405 => MethodNotAllowedException::class,
            $response->status === 406 => NotAcceptableException::class,
            $response->status === 409 => ConflictException::class,
            $response->status === 410 => GoneException::class,
            $response->status === 415 => UnsupportedMediaTypeException::class,
            $response->status === 422 => ValidationException::class,
            $response->status === 429 => RateLimitException::class,
            $response->status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class(self::buildMessage($request, $response, $errors), $request, $response, $errors);
    }

    public function status(): int
    {
        return $this->response->status;
    }

    public function firstError(): ?ErrorObject
    {
        return $this->errors[0] ?? null;
    }

    /**
     * True when any error carries the given code or title, e.g. "keyset_unsupported_sort".
     */
    public function hasError(string $codeOrTitle): bool
    {
        foreach ($this->errors as $error) {
            if ($error->code === $codeOrTitle || $error->title === $codeOrTitle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ErrorObject>
     */
    private static function parseErrors(Response $response): array
    {
        $errors = array_filter(self::decodeErrors($response), is_array(...));

        return array_values(array_map(ErrorObject::fromArray(...), $errors));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeErrors(Response $response): array
    {
        try {
            $errors = $response->json()['errors'] ?? [];
        } catch (InvalidResponseException) {
            return [];
        }

        return is_array($errors) ? $errors : [];
    }

    /**
     * @param  list<ErrorObject>  $errors
     */
    private static function buildMessage(Request $request, Response $response, array $errors): string
    {
        $first = $errors[0] ?? null;
        $title = $first?->title !== null ? ' ' . $first->title : '';
        $details = array_map(fn(ErrorObject $error): string => self::describeError($error), $errors);
        $details = array_unique(array_filter($details, fn(string $detail): bool => $detail !== ''));

        return sprintf(
            'Productive API error %d%s%s [%s]',
            $response->status,
            $title,
            $details === [] ? '' : ': ' . implode('; ', $details),
            $request->describe(),
        );
    }

    private static function describeError(ErrorObject $error): string
    {
        if ($error->detail === null) {
            return '';
        }

        $attribute = $error->attribute();

        return $attribute !== null && ! str_contains($error->detail, $attribute)
            ? $attribute . ' ' . $error->detail
            : $error->detail;
    }
}
