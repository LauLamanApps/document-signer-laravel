<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Http\Controllers;

use LauLamanApps\DocumentSigner\Laravel\Events\DocumentSignerWebhookReceived;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\DocuSignSignatureVerifier;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\ValidSignSignatureVerifier;
use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\WebhookSignatureVerifier;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives provider-side status callbacks, verifies the shared-secret signature,
 * and re-emits the payload as a {@see DocumentSignerWebhookReceived} event so
 * application code can update its own state without coupling to the SDK.
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
        );
    }

    private function handle(Request $request, string $driver, WebhookSignatureVerifier $verifier): JsonResponse
    {
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
        ));

        return new JsonResponse(['ok' => true]);
    }
}
