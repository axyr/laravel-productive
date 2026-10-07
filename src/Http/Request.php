<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

/**
 * An API request, described independently of the HTTP client that sends it.
 */
final readonly class Request
{
    /**
     * @param  string  $path  Relative to the configured base URL, or an absolute URL taken from a `links.next`.
     * @param  string  $query  Already serialized query string, without the leading "?".
     * @param  array<string, mixed>|null  $body  JSON:API document to send.
     * @param  string  $operation  Stable operation name such as "tasks.reposition", used by the testing fake.
     * @param  list<RateLimit>  $rateLimits  Buckets on top of the per-token limit every request counts against.
     */
    public function __construct(
        public Method $method,
        public string $path,
        public string $query = '',
        public ?array $body = null,
        public ContentType $contentType = ContentType::JsonApi,
        public Expect $expect = Expect::Resource,
        public string $operation = '',
        public bool $requiresOrganization = true,
        public array $rateLimits = [],
    ) {}

    public function isAbsolute(): bool
    {
        return str_starts_with($this->path, 'https://') || str_starts_with($this->path, 'http://');
    }

    /**
     * A credential-free description for exception messages and logs, e.g. "PATCH tasks/1/reposition".
     */
    public function describe(): string
    {
        return $this->method->value . ' ' . $this->path;
    }
}
