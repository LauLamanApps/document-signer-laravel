<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | Which driver `DocumentSigner::send()` resolves to when no explicit
    | `driver(...)` call is made.
    |
    | When left `null` (the default), the manager auto-selects the sole
    | configured driver — the one whose primary credential is set. If
    | multiple drivers are configured, the manager throws on the first
    | implicit `send()` call and asks you to set `DOCUMENT_SIGNER_DRIVER`
    | explicitly.
    |
    */

    'default' => env('DOCUMENT_SIGNER_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'validsign' => [
            'api_key'           => env('VALIDSIGN_API_KEY'),
            'base_url'          => env('VALIDSIGN_BASE_URL', 'https://my.validsign.nl/api'),
            'default_language'  => env('VALIDSIGN_DEFAULT_LANGUAGE', 'nl'),
            'timeout'           => (int) env('VALIDSIGN_TIMEOUT', 15),
            'upload_timeout'    => (int) env('VALIDSIGN_UPLOAD_TIMEOUT', 60),
        ],

        'docusign' => [
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The service provider auto-registers a webhook route for every driver
    | whose webhook secret is set. Setting a secret is what enables the
    | webhook — there's no separate on/off flag, because a webhook with no
    | secret would 401 every request anyway. Set:
    |  - `DOCUSIGN_CONNECT_HMAC_SECRET` to enable the DocuSign webhook.
    |  - `VALIDSIGN_CALLBACK_SECRET` to enable the ValidSign webhook.
    |
    | If neither is set, no webhook routes are registered at all — useful
    | when callbacks are handled by a separate service (queue worker, edge
    | function, external ingest) or in local development.
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

    'webhooks' => [

        'prefix'     => env('DOCUMENT_SIGNER_WEBHOOK_PREFIX', 'document-signer/webhooks'),
        'middleware' => ['api'],

        'docusign' => [
            'hmac_secret' => env('DOCUSIGN_CONNECT_HMAC_SECRET'),
        ],

        'validsign' => [
            'callback_secret' => env('VALIDSIGN_CALLBACK_SECRET'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | PDF renderer
    |--------------------------------------------------------------------------
    |
    | Selects which PdfRenderer the manager wires into every driver.
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
