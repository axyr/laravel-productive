<?php

declare(strict_types=1);

namespace Axyr\Productive\Http;

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Contracts\ThrottleInterface;
use Axyr\Productive\Exceptions\ApiException;
use Axyr\Productive\Exceptions\ConnectionException;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Axyr\Productive\Version;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Sleep;

/**
 * The only class that talks to the HTTP client.
 */
final readonly class Connector implements ConnectorInterface
{
    public function __construct(
        private ProductiveConfig $config,
        private Factory $http,
        private ThrottleInterface $throttle,
        private RetryPolicy $retryPolicy,
    ) {}

    public function send(Request $request): Response
    {
        $this->config->assertHasCredentials($request->requiresOrganization);
        $url = $this->url($request);
        $attempt = 1;

        while (true) {
            $this->throttle->acquire($request, $this->config->tokenFingerprint());

            try {
                return $this->attempt($request, $url);
            } catch (ApiException|ConnectionException $exception) {
                $delay = $this->retryDelay($request, $exception, $attempt) ?? throw $exception;
            }

            Sleep::for($delay)->seconds();
            $attempt++;
        }
    }

    private function retryDelay(Request $request, ApiException|ConnectionException $exception, int $attempt): ?int
    {
        return $exception instanceof ApiException
            ? $this->retryPolicy->delayAfterError($request, $exception, $attempt)
            : $this->retryPolicy->delayAfterConnectionFailure($request, $attempt);
    }

    private function attempt(Request $request, string $url): Response
    {
        try {
            $httpResponse = $this->pendingRequest($request)->send($request->method->value, $url);
        } catch (HttpConnectionException $exception) {
            throw new ConnectionException(sprintf('Could not reach Productive [%s]: %s', $request->describe(), $exception->getMessage()), previous: $exception);
        }

        /** @var array<string, list<string>> $headers */
        $headers = $httpResponse->headers();
        $response = new Response($httpResponse->status(), $headers, $httpResponse->body());

        if (! $response->successful()) {
            throw ApiException::fromResponse($request, $response);
        }

        return $response;
    }

    private function pendingRequest(Request $request): PendingRequest
    {
        $pending = $this->http
            ->withHeaders($this->headers($request))
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);

        if ($request->body === null) {
            return $pending;
        }

        return $pending->withBody(
            json_encode($request->body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES),
            $request->contentType->value,
        );
    }

    private function url(Request $request): string
    {
        if (! $request->isAbsolute()) {
            $url = $this->config->url($request->path);

            // An empty query yields "url?", which every HTTP client normalizes to "url".
            return $request->query === '' ? $url : $url . '?' . $request->query; // @pest-mutate-ignore
        }

        if (! $this->config->ownsUrl($request->path)) {
            throw new InvalidResponseException(sprintf(
                'Refusing to send credentials to "%s": it is not under the configured Productive base URL.',
                $request->path,
            ));
        }

        return $request->path;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [
            'X-Auth-Token' => $this->config->token,
            'Accept' => $request->expect === Expect::Binary ? '*/*' : ContentType::JsonApi->value,
            'User-Agent' => 'axyr/laravel-productive/' . Version::VERSION,
        ];

        if ($request->requiresOrganization) {
            $headers['X-Organization-Id'] = $this->config->organizationId;
        }

        if ($this->config->featureFlags !== []) {
            $headers['X-Feature-Flags'] = implode(',', $this->config->featureFlags);
        }

        return $headers;
    }
}
