<?php

declare(strict_types=1);

namespace Axyr\Productive\Exceptions;

/**
 * HTTP 422. One or more attributes were rejected.
 */
class ValidationException extends ApiException
{
    /**
     * Error messages keyed by attribute name. Errors without a source pointer are keyed by "base".
     *
     * @return array<string, list<string>>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->errors as $error) {
            $messages[$error->attribute() ?? 'base'][] = $error->message();
        }

        return $messages;
    }

    public function first(string $attribute): ?string
    {
        return $this->messages()[$attribute][0] ?? null;
    }
}
