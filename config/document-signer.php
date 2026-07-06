<?php

declare(strict_types=1);

use LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | Which provider `DocumentSigner::send()` resolves to when no explicit
    | `driver(...)` call is made. The value is a provider's short name — the
    | `NAME` constant on its class (`validsign`, `docusign`, ...).
    |
    | When left `null` (the default), the manager auto-selects the sole
    | configured provider — the one whose primary credential is set. If
    | multiple providers are configured, the manager throws on the first
    | implicit `send()` call and asks you to set `DOCUMENT_SIGNER_DRIVER`
    | explicitly.
    |
    */

    'default' => env('DOCUMENT_SIGNER_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Each entry wires one signature provider. Everything a provider needs
    | lives in a single place:
    |
    |  - `class`   the SignatureProvider implementation. Its `NAME` constant
    |              is the short name used by `default`, `driver('...')` and the
    |              webhook route (`/{prefix}/{name}`).
    |  - `config`  the credentials/options passed to the provider.
    |  - `webhook` the shared secret that enables (and verifies) its webhook.
    |
    | To add an app-owned provider, append an entry pointing `class` at your
    | own SignatureProvider — no code change here is required. It is built
    | through the container, so its dependencies auto-wire and the
    | integration-managed PdfRenderer is injected as `$pdfRenderer`.
    |
    | Referencing a provider class here is safe even when its package is not
    | installed: `::class` is a compile-time string and never loads the class.
    | The manager surfaces a `composer require ...` hint if you select one
    | whose package is missing.
    |
    */

    'providers' => [

        [
            'class'  => ValidSignProvider::class,
            'config' => [
                'api_key'           => env('VALIDSIGN_API_KEY'),
                'base_url'          => env('VALIDSIGN_BASE_URL', 'https://my.validsign.nl/api'),
                'default_language'  => env('VALIDSIGN_DEFAULT_LANGUAGE', 'nl'),
                'timeout'           => (int) env('VALIDSIGN_TIMEOUT', 15),
                'upload_timeout'    => (int) env('VALIDSIGN_UPLOAD_TIMEOUT', 60),
            ],
            'webhook' => [
                'callback_secret' => env('VALIDSIGN_CALLBACK_SECRET'),
            ],
        ],

        [
            'class'  => DocuSignProvider::class,
            'config' => [
                'integration_key'    => env('DOCUSIGN_INTEGRATION_KEY'),
                'user_id'            => env('DOCUSIGN_USER_ID'),
                'account_id'         => env('DOCUSIGN_ACCOUNT_ID'),

                // Provide one of these. `private_key_path` takes precedence when both are set.
                'private_key'        => env('DOCUSIGN_PRIVATE_KEY'),
                'private_key_path'   => env('DOCUSIGN_PRIVATE_KEY_PATH'),

                'oauth_base_url'     => env('DOCUSIGN_OAUTH_BASE_URL', 'account-d.docusign.com'),
                'api_base_url'       => env('DOCUSIGN_API_BASE_URL', 'https://demo.docusign.net/restapi'),
                'scopes'             => env('DOCUSIGN_SCOPES', 'signature impersonation'),

                'access_token_ttl'   => (int) env('DOCUSIGN_ACCESS_TOKEN_TTL', 3600),
                'timeout'            => (int) env('DOCUSIGN_TIMEOUT', 15),
                'upload_timeout'     => (int) env('DOCUSIGN_UPLOAD_TIMEOUT', 60),
            ],
            'webhook' => [
                'hmac_secret' => env('DOCUSIGN_CONNECT_HMAC_SECRET'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook routing
    |--------------------------------------------------------------------------
    |
    | The service provider auto-registers a webhook route for every provider
    | whose `webhook` secret is set. Setting a secret is what enables the
    | webhook — there's no separate on/off flag, because a webhook with no
    | secret would 401 every request anyway. Set:
    |  - `DOCUSIGN_CONNECT_HMAC_SECRET` to enable the DocuSign webhook.
    |  - `VALIDSIGN_CALLBACK_SECRET` to enable the ValidSign webhook.
    |
    | If no provider has a secret set, no webhook routes are registered at all
    | — useful when callbacks are handled by a separate service (queue worker,
    | edge function, external ingest) or in local development.
    |
    | Both providers use a shared-secret model:
    |  - DocuSign Connect signs the request body with HMAC-SHA256 and sends
    |    the base64 result in `X-DocuSign-Signature-1`.
    |  - ValidSign callbacks send the configured secret in the
    |    `Authorization: Basic <credentials>` header. The verifier accepts
    |    `base64("username:secret")`, `base64(secret)`, or the raw string
    |    after `Basic `, to cover the encodings different tenants use.
    |
    | Unverified requests are rejected with HTTP 401.
    |
    */

    'routing' => [
        'prefix'     => env('DOCUMENT_SIGNER_WEBHOOK_PREFIX', 'document-signer/webhooks'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF renderer
    |--------------------------------------------------------------------------
    |
    | Selects which PdfRenderer the manager wires into every provider.
    |
    |  - `browsershot` (default) uses the SDK's BrowsershotPdfRenderer; needs
    |    Node.js + Puppeteer.
    |  - `laravel-pdf` uses spatie/laravel-pdf, which itself wraps Browsershot
    |    but respects any laravel-pdf bindings/macros your application has
    |    configured. Requires `composer require spatie/laravel-pdf`.
    |
    | To fully replace the renderer (different engine, custom configuration),
    | bind your own implementation in a service provider — the manager picks
    | up the container binding first and ignores this config value when found:
    |
    |   $this->app->bind(
    |       \LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer::class,
    |       MyPdfRenderer::class,
    |   );
    |
    */

    'pdf' => [
        'renderer' => env('DOCUMENT_SIGNER_PDF_RENDERER', 'browsershot'),
    ],

];
