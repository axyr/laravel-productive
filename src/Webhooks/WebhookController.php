<?php

declare(strict_types=1);

namespace Axyr\Productive\Webhooks;

use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Exceptions\InvalidResponseException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives a verified delivery, hydrates it and dispatches ProductiveWebhookReceived.
 * Answers 200 so Productive does not retry; 400 for a body that is not a delivery.
 */
final readonly class WebhookController
{
    public function __construct(
        private ModelRegistry $registry,
        private Dispatcher $events,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        try {
            $delivery = WebhookDelivery::fromPayload(is_array($payload) ? $payload : [], $this->registry);
        } catch (InvalidResponseException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->events->dispatch(new ProductiveWebhookReceived($delivery));

        return new JsonResponse(['received' => true]);
    }
}
