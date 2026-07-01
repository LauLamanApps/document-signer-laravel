# Laravel integration for the document signer SDK

Laravel integration for the [Document Signer SDK](https://github.com/LauLamanApps/document-signer-sdk): config, service
provider, driver manager, facade, and verified webhook routes for both
ValidSign and DocuSign.

## Install

```bash
composer require laulamanapps/document-signer-laravel
```

Then add at least one provider package — the laravel package treats both as
optional:

```bash
composer require laulamanapps/document-signer-validsign   # for the validsign driver
composer require laulamanapps/document-signer-docusign    # for the docusign driver
```

Publish the config:

```bash
php artisan vendor:publish --tag=document-signer-config
```

## Configure

`config/document-signer.php` reads from `.env`. Minimum for ValidSign:

```dotenv
VALIDSIGN_API_KEY=your-base64-key
```

Minimum for DocuSign:

```dotenv
DOCUSIGN_INTEGRATION_KEY=...
DOCUSIGN_USER_ID=...
DOCUSIGN_ACCOUNT_ID=...
DOCUSIGN_PRIVATE_KEY_PATH=/path/to/private.pem
```

### Default driver

`DOCUMENT_SIGNER_DRIVER` is optional. If left unset the manager auto-selects
the sole configured driver — i.e. the one whose primary credential
(`VALIDSIGN_API_KEY` or `DOCUSIGN_INTEGRATION_KEY`) is present. Set the
variable explicitly only when you configure both providers in the same app:

```dotenv
# Both configured — pick the implicit default:
DOCUMENT_SIGNER_DRIVER=validsign
```

Without an explicit choice in the "both configured" case, the first implicit
`DocumentSigner::send()` call throws with the list of configured drivers.

## Sending an envelope

```php
use LauLamanApps\DocumentSigner\Laravel\Facades\DocumentSigner;
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;

$receipt = DocumentSigner::send(new Envelope(
    name:         'NDA',
    documents:    [new Document(
        id:   'nda',
        name: 'NDA',
        html: '<p>{[signature:counterparty:sig]} on {[date:counterparty:signdate]}</p>',
    )],
    signers:      [new Signer(key: 'counterparty', name: 'Jane Doe', email: 'jane@example.com')],
    emailSubject: 'Please sign the NDA',
));

// Switch driver at runtime:
$receipt = DocumentSigner::driver('docusign')->send($envelope);
```

You can also type-hint the manager directly:

```php
use LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager;

public function __construct(private DocumentSignerManager $signer) {}
```

## Blade components

The raw `{[type:signer:name]}` syntax is safe to type inside `.blade.php`
files — `{[` / `]}` doesn't collide with Blade's `{{ }}` echo tags. For
ergonomics, though, the package ships five anonymous components that compile
to the raw placeholder token so contracts read like normal HTML:

```blade
<h1>Mutual NDA</h1>

<p>I, <x-document-signer::text signer="counterparty" name="fullname" />, agree.</p>

<p>Signed: <x-document-signer::signature signer="counterparty" name="sig" />
   on <x-document-signer::date signer="counterparty" name="signdate" /></p>

<p><x-document-signer::checkbox signer="counterparty" name="opt_in" />
   I would like to receive updates.</p>
```

| Component | Compiles to |
| --- | --- |
| `<x-document-signer::signature signer="…" name="…" />` | `{[signature:…:…]}` |
| `<x-document-signer::initials signer="…" name="…" />` | `{[initials:…:…]}` |
| `<x-document-signer::text signer="…" name="…" />` | `{[text:…:…]}` |
| `<x-document-signer::date signer="…" name="…" />` | `{[date:…:…]}` |
| `<x-document-signer::checkbox signer="…" name="…" />` | `{[checkbox:…:…]}` |

The components are registered under the `document-signer::` namespace by the
service provider. Both `signer` and `name` are required attributes. After the
view renders, the resulting HTML is what you pass to `Document::$html` — the
SDK parser sees the literal `{[type:signer:name]}` tokens and proceeds as
usual.

## PDF renderer

The manager wires a [`PdfRenderer`](https://github.com/LauLamanApps/document-signer-sdk/blob/main/src/Pdf/PdfRenderer.php)
into every driver it resolves. By default it uses the SDK's
`BrowsershotPdfRenderer`. Two other options are built in.

### Default: install spatie/browsershot

The SDK bundles the `BrowsershotPdfRenderer` class but not the Composer
dependency — you need to install it explicitly if you want to keep the
default:

```bash
composer require spatie/browsershot
```

Without it the manager throws an `InvalidArgumentException` pointing at the
install command the first time it tries to build the renderer.

### Use spatie/laravel-pdf

If your application already configures
[spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) — custom Node
binary, default paper size, headers/footers, Browsershot tweaks — switch the
SDK over so it picks up that configuration:

```bash
composer require spatie/laravel-pdf
```

```dotenv
DOCUMENT_SIGNER_PDF_RENDERER=laravel-pdf
```

The SDK then renders every envelope document through the `Pdf` facade. If
`laravel-pdf` isn't installed when this option is selected, the manager
throws an `InvalidArgumentException` pointing at the install command.

### Bind a fully custom renderer

For any other engine (wkhtmltopdf, Gotenberg, an external service, a tuned
Browsershot setup), implement the SDK's `PdfRenderer` interface and bind it
in a service provider — the manager picks up the container binding first and
ignores the config value:

```php
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use App\Pdf\GotenbergRenderer;

$this->app->bind(PdfRenderer::class, GotenbergRenderer::class);
```

See [Writing a custom renderer](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/pdf-rendering.md)
for the interface and an example.

## Webhooks

A webhook route is auto-registered for each driver whose primary credential
is configured. There is no separate enable flag — if you set up DocuSign,
you get the DocuSign webhook; if you set up ValidSign, you get the ValidSign
webhook. If neither driver is configured, no routes are registered at all.

DocuSign only:

```dotenv
DOCUSIGN_INTEGRATION_KEY=...
DOCUSIGN_CONNECT_HMAC_SECRET=...
```

ValidSign only:

```dotenv
VALIDSIGN_API_KEY=...
VALIDSIGN_CALLBACK_SECRET=...
```

The common prefix (default `document-signer/webhooks`) and middleware
(default `['api']`) live under `document-signer.webhooks` in the config file.

| Provider | Registered when | Route name | URL | Signature mechanism |
| --- | --- | --- | --- | --- |
| DocuSign | `DOCUSIGN_INTEGRATION_KEY` is set | `document-signer.webhooks.docusign` | `POST /document-signer/webhooks/docusign` | HMAC-SHA256 of raw body in `X-DocuSign-Signature-1..N` |
| ValidSign | `VALIDSIGN_API_KEY` is set | `document-signer.webhooks.validsign` | `POST /document-signer/webhooks/validsign` | Shared secret in `?token=`, `X-Callback-Key`, or `X-Callback-Token` |

Both verifiers use `hash_equals` for constant-time comparison and reject
unverified requests with HTTP 401. The webhook will still 401 every request
until you also set its signing secret (`DOCUSIGN_CONNECT_HMAC_SECRET` /
`VALIDSIGN_CALLBACK_SECRET`).

Listen to the event in `app/Providers/EventServiceProvider.php`:

```php
use LauLamanApps\DocumentSigner\Laravel\Events\DocumentSignerWebhookReceived;

protected $listen = [
    DocumentSignerWebhookReceived::class => [
        \App\Listeners\HandleSignerWebhook::class,
    ],
];
```

```php
use LauLamanApps\DocumentSigner\Laravel\Events\DocumentSignerWebhookReceived;

final class HandleSignerWebhook
{
    public function handle(DocumentSignerWebhookReceived $event): void
    {
        match ($event->driver) {
            'docusign'  => $this->onDocuSign($event->payload),
            'validsign' => $this->onValidSign($event->payload),
        };
    }
}
```

## Testing

Swap the live provider for a fake in tests:

```php
use LauLamanApps\DocumentSigner\Laravel\Facades\DocumentSigner;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt;
use LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider;

DocumentSigner::set('validsign', new class implements SignatureProvider {
    public function send($envelope): EnvelopeReceipt {
        return new EnvelopeReceipt(
            provider: 'validsign',
            providerEnvelopeId: 'test-id',
            status: EnvelopeStatus::Sent,
        );
    }
    public function getStatus(string $id): EnvelopeStatus { return EnvelopeStatus::Completed; }
    public function downloadSigned(string $id): \SplFileInfo { return new \SplFileInfo('/dev/null'); }
    public function downloadAudit(string $id): \SplFileInfo { return new \SplFileInfo('/dev/null'); }
    public function getFieldValues(string $id): array { return []; }
    public function cancel(string $id, ?string $reason = null): void {}
});
```

For full end-to-end provider mocking, see
[Extending the SDK](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/extending.md).

## Requirements

- PHP 8.5
- Laravel 13
- `laulamanapps/documentsigner-sdk`
- `laulamanapps/documentsigner-validsign` *or* `laulamanapps/documentsigner-docusign` (each is optional;
  installed only for the drivers you actually use — the manager throws a clear
  `composer require` hint if a missing driver is requested)
- Node.js + Puppeteer (for the default Browsershot renderer)

## Documentation

- [SDK README](https://github.com/LauLamanApps/document-signer-sdk)
- [Getting started](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/getting-started.md)
- [ValidSign provider guide](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/providers/validsign.md)
- [DocuSign provider guide](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/providers/docusign.md)
- [Placeholder syntax](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/placeholders.md)
- [Extending the SDK](https://github.com/LauLamanApps/document-signer-sdk/blob/main/docs/extending.md)
