# Upgrade guide

## 1.x → 2.0

2.0 restructures the config file and the webhook event so provider
configuration is cohesive and any `SignatureProvider` — including your own —
can be plugged in. **The `.env` variables are unchanged**, so a fresh install
using the shipped config keeps working after you re-publish it. The manual work
is: migrate a *published* config file, and update webhook listeners.

Requires `document-signer-sdk:^2.0` (and, if installed, `document-signer-validsign:^2.0`
/ `document-signer-docusign:^2.0`). `composer update` pulls them in.

---

### 1. Config: `drivers` + `webhooks` → `providers` + `routing`

The keyed `drivers` map and the separate `webhooks` section are gone. Each
provider is now one entry in a `providers` **list** that co-locates its class,
credentials, and webhook secret; routing lives under `routing`.

If you published the config, re-publish and re-apply your env references, or
hand-migrate:

```php
// BEFORE — config/document-signer.php (1.x)
'drivers' => [
    'validsign' => [
        'api_key'  => env('VALIDSIGN_API_KEY'),
        'base_url' => env('VALIDSIGN_BASE_URL', 'https://my.validsign.nl/api'),
        // ...
    ],
    'docusign' => [
        'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),
        // ...
    ],
],
'webhooks' => [
    'prefix'     => env('DOCUMENT_SIGNER_WEBHOOK_PREFIX', 'document-signer/webhooks'),
    'middleware' => ['api'],
    'validsign'  => ['callback_secret' => env('VALIDSIGN_CALLBACK_SECRET')],
    'docusign'   => ['hmac_secret'     => env('DOCUSIGN_CONNECT_HMAC_SECRET')],
],
```

```php
// AFTER — config/document-signer.php (2.0)
'providers' => [
    [
        'class'   => \LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider::class,
        'config'  => [
            'api_key'  => env('VALIDSIGN_API_KEY'),
            'base_url' => env('VALIDSIGN_BASE_URL', 'https://my.validsign.nl/api'),
            // ...
        ],
        'webhook' => ['callback_secret' => env('VALIDSIGN_CALLBACK_SECRET')],
    ],
    [
        'class'   => \LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider::class,
        'config'  => ['integration_key' => env('DOCUSIGN_INTEGRATION_KEY'), /* ... */],
        'webhook' => ['hmac_secret' => env('DOCUSIGN_CONNECT_HMAC_SECRET')],
    ],
],
'routing' => [
    'prefix'     => env('DOCUMENT_SIGNER_WEBHOOK_PREFIX', 'document-signer/webhooks'),
    'middleware' => ['api'],
],
```

Unchanged: `DOCUMENT_SIGNER_DRIVER` still selects by the provider's short name
(`validsign` / `docusign`), auto-selection of the sole configured provider,
webhook route names/paths, and every `.env` variable.

**Custom drivers:** the old `drivers.<name>.provider` class-string key is
replaced by the entry's `class`. A provider's short name is now its
`public const string NAME`, not the array key — make sure your provider
declares one. See the README's "Custom providers" section.

---

### 2. `DocumentSignerWebhookReceived`: `$driver` → `$provider`

The `$driver` property (short name string) is replaced by `$provider` (a
provider **class-string**), and it moved to the first constructor argument.

```php
// before
$event->driver;                     // 'validsign'

// after
$event->provider;                   // ValidSignProvider::class
$event->provider::NAME;             // 'validsign'  (short name, if you need it)
$event->provider === ValidSignProvider::class;   // unambiguous branch
```

The event no longer exposes `provider()` on `$event->event` (the SDK dropped
it) — read `$event->provider` instead, which is **always set even when
`$event->event` is `null`**.

If you construct this event yourself (unusual — normally the package does),
add the `provider:` argument.

---

### 3. `EventTranslator::label()` takes the provider

Because the event no longer carries its provider, the translator receives it
explicitly:

```php
// before
$labels->label($event->event);
$labels->label($event->event, locale: 'nl');

// after
$labels->label($event->event, $event->provider);
$labels->label($event->event, $event->provider, locale: 'nl');
```

---

### 4. Webhook events are non-null for first-party providers

Both ValidSign and DocuSign now resolve every verified callback to a
`WebhookEvent` case (unmatched tokens → their enum's `Unknown` case), so
`$event->event` is non-null for them. Only a custom provider that returns
`null` from `resolveWebhookEvent()` (or ships no enum) yields
`$event->event === null`. Keep using the null-safe operator if you handle
custom providers:

```php
match (true) {
    $event->event?->isCompleted() => /* ... */,
    $event->event?->isDeclined()  => /* ... */,
    default                       => null,
};
```

DocuSign listeners that previously relied on `$event->event === null` (there was
no DocuSign enum in 1.x) should switch to the semantic predicates or match on
`DocuSign\Webhook\EventType` cases directly.

---

### New in 2.0 (no action required)

- **Custom providers**: add any `SignatureProvider` to `providers` and select
  it by its `NAME`; `config` is injected into the constructor. Implement
  `Http\Webhook\ProvidesWebhook` to get an auto-registered, verified webhook
  route. See the README.
- **DocuSign event enum + translations** (`DocuSign\Webhook\EventType`,
  `docusign-events.php` in `en`/`nl`).
