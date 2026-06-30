<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

final class DocumentSignerWebhookReceived
{
    use Dispatchable;

    /**
     * @param string                $driver  Driver key the webhook came from (e.g. `validsign`, `docusign`).
     * @param array<string, mixed>  $payload Parsed JSON body. Empty for `application/xml` callbacks; consult `$request` for those.
     * @param Request               $request Original HTTP request, for callers that need raw access (XML body, headers).
     */
    public function __construct(
        public readonly string  $driver,
        public readonly array   $payload,
        public readonly Request $request,
    ) {}
}
