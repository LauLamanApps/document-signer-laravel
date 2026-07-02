<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use LauLamanApps\DocumentSigner\Sdk\Webhook\WebhookEvent;

final class DocumentSignerWebhookReceived
{
    use Dispatchable;

    /**
     * @param string                $driver  Driver key the webhook came from (e.g. `validsign`, `docusign`).
     * @param array<string, mixed>  $payload Parsed JSON body. Empty for `application/xml` callbacks; consult `$request` for those.
     * @param Request               $request Original HTTP request, for callers that need raw access (XML body, headers).
     * @param WebhookEvent|null     $event   Resolved provider-native event token when the payload's `name`/`event`/... field
     *                                       matches a known enum case. `null` when the driver has no `WebhookEvent` enum
     *                                       yet, or when the value doesn't match any known case. Consumers can safely rely
     *                                       on the semantic predicates (`->isCompleted()`, `->isDeclined()`, ...) via null-safe
     *                                       calls: `$event->event?->isCompleted()`.
     */
    public function __construct(
        public readonly string        $driver,
        public readonly array         $payload,
        public readonly Request       $request,
        public readonly ?WebhookEvent $event = null,
    ) {}
}
