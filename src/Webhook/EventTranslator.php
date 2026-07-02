<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Webhook;

use Illuminate\Contracts\Translation\Translator;
use LauLamanApps\DocumentSigner\Sdk\Webhook\WebhookEvent;

/**
 * Turns a resolved {@see WebhookEvent} into a human-readable label using the
 * package's translation files.
 *
 * Translation keys live under `resources/lang/{locale}/{provider}-events.php`
 * (e.g. `validsign-events.PACKAGE_COMPLETE`) and are exposed under the
 * `document-signer` namespace, so app code can override any string by
 * publishing the files:
 *
 *     php artisan vendor:publish --tag=document-signer-translations
 */
final readonly class EventTranslator
{
    public function __construct(private Translator $translator) {}

    /**
     * Returns the translated label for `$event` in `$locale` (or the app's
     * current locale when omitted).
     *
     * Falls back to the raw provider token (`PACKAGE_COMPLETE`) when no
     * translation exists — never returns a "translation missing" placeholder,
     * so listeners can log the result safely.
     */
    public function label(WebhookEvent $event, ?string $locale = null): string
    {
        $key = sprintf('document-signer::%s-events.%s', $event->provider(), $event->value());

        $translated = $this->translator->get($key, [], $locale);

        // Laravel returns the untouched key when it can't find a translation.
        return is_string($translated) && $translated !== $key
            ? $translated
            : $event->value();
    }
}
