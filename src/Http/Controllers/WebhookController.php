<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Http\Controllers;

use LauLamanApps\DocumentSigner\Laravel\Events\DocumentSignerWebhookReceived;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\DocuSignSignatureVerifier;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\ValidSignSignatureVerifier;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\WebhookSignatureVerifier;
use LauLamanApps\DocumentSigner\Sdk\Webhook\WebhookEvent;
use LauLamanApps\DocumentSigner\ValidSign\Webhook\EventType as ValidSignEventType;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives provider-side status callbacks, verifies the shared-secret signature,
 * and re-emits the payload as a {@see DocumentSignerWebhookReceived} event so
 * application code can update its own state without coupling to the SDK.
 *
 * When the provider ships a {@see WebhookEvent} enum (currently ValidSign; DocuSign
 * to follow), the controller resolves the callback token against it before
 * dispatching, so listeners can use the semantic predicates
 * (`$event->event?->isCompleted()`, `->isDeclined()`, …) directly without doing
 * the enum look-up themselves.
 */
final class WebhookController
{
    public function __construct(
        private readonly Config     $config,
        private readonly Dispatcher $events,
    ) {}

    public function docusign(Request $request): JsonResponse
    {
        return $this->handle(
            $request,
            driver: 'docusign',
            verifier: new DocuSignSignatureVerifier(
                $this->config->get('document-signer.webhooks.docusign.hmac_secret'),
            ),
            // DocuSign doesn't ship a WebhookEvent enum yet; listeners fall back to $event->payload.
            resolveEvent: static fn (array $_): ?WebhookEvent => null,
        );
    }

    public function validsign(Request $request): JsonResponse
    {
        return $this->handle(
            $request,
            driver: 'validsign',
            verifier: new ValidSignSignatureVerifier(
                (string) ($this->config->get('document-signer.webhooks.validsign.callback_secret') ?? ''),
            ),
            resolveEvent: static fn (array $payload): ?WebhookEvent =>
                class_exists(ValidSignEventType::class)
                    ? ValidSignEventType::tryFromPayload($payload)
                    : null,
        );
    }

    /**
     * @param \Closure(array<string, mixed>): ?WebhookEvent $resolveEvent
     */
    private function handle(
        Request $request,
        string $driver,
        WebhookSignatureVerifier $verifier,
        \Closure $resolveEvent,
    ): JsonResponse {
        if (!$verifier->verify($request)) {
            return new JsonResponse(['error' => 'invalid_signature'], 401);
        }

        $payload = [];
        $contentType = (string) ($request->header('Content-Type') ?? '');
        if (str_contains($contentType, 'json')) {
            try {
                $decoded = json_decode($request->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (\JsonException) {
                return new JsonResponse(['error' => 'invalid_json'], 400);
            }
        }

        $this->events->dispatch(new DocumentSignerWebhookReceived(
            driver: $driver,
            payload: $payload,
            request: $request,
            event: $resolveEvent($payload),
        ));

        return new JsonResponse(['ok' => true]);
    }
}
