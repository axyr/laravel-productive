<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

use Axyr\Productive\Exceptions\WebhookException;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects a delivery whose `Productive-Signature` no configured signature token verifies.
 */
final readonly class VerifyProductiveSignature
{
    public function __construct(private Repository $config) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->signature()->verify($request->header(WebhookSignature::HEADER), $request->getContent())) {
            return new JsonResponse(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function signature(): WebhookSignature
    {
        $secrets = $this->config->get('productive.webhooks.secrets');
        $secrets = array_filter(array_map(trim(...), explode(',', is_string($secrets) ? $secrets : '')));

        if ($secrets === []) {
            throw new WebhookException('No webhook signature token is configured: set PRODUCTIVE_WEBHOOK_SECRETS to the token(s) shown in Productive\'s webhook logs.');
        }

        $tolerance = $this->config->get('productive.webhooks.tolerance');

        return new WebhookSignature($secrets, is_numeric($tolerance) ? (int) $tolerance : 300);
    }
}
