<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Tests\Webhook;

use LauLamanApps\DocumentSigner\Laravel\DocumentSignerServiceProvider;
use LauLamanApps\DocumentSigner\Laravel\Webhook\EventTranslator;
use LauLamanApps\DocumentSigner\Sdk\Webhook\WebhookEvent;
use LauLamanApps\DocumentSigner\ValidSign\Webhook\EventType;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EventTranslatorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DocumentSignerServiceProvider::class];
    }

    #[Test]
    public function it_translates_a_validsign_event_using_the_english_fallback(): void
    {
        $this->app->setLocale('en');

        $translator = new EventTranslator($this->app->make('translator'));

        self::assertSame('Package complete',       $translator->label(EventType::PackageComplete));
        self::assertSame('Signer declined the package', $translator->label(EventType::PackageDecline));
        self::assertSame('Knowledge-based authentication failed', $translator->label(EventType::KbaFailure));
    }

    #[Test]
    public function it_translates_a_validsign_event_using_the_dutch_locale(): void
    {
        $translator = new EventTranslator($this->app->make('translator'));

        self::assertSame('Pakket voltooid', $translator->label(EventType::PackageComplete, 'nl'));
        self::assertSame(
            'Ondertekenaar heeft het pakket geweigerd',
            $translator->label(EventType::PackageDecline, 'nl'),
        );
    }

    #[Test]
    public function it_falls_back_to_the_raw_provider_token_when_no_translation_exists(): void
    {
        $translator = new EventTranslator($this->app->make('translator'));

        $event = new class implements WebhookEvent {
            public function value(): string    { return 'UNKNOWN_ONE'; }
            public function provider(): string { return 'validsign'; }
            public function isCompleted(): bool { return false; }
            public function isDeclined(): bool  { return false; }
            public function isFailure(): bool   { return false; }
            public function isProgress(): bool  { return false; }
        };

        // Never returns Laravel's "translation missing" key — always a printable label.
        self::assertSame('UNKNOWN_ONE', $translator->label($event));
    }

    #[Test]
    public function every_validsign_case_has_an_english_translation(): void
    {
        $translator = new EventTranslator($this->app->make('translator'));

        foreach (EventType::cases() as $case) {
            $label = $translator->label($case, 'en');
            self::assertNotSame(
                $case->value,
                $label,
                sprintf('Missing English translation for %s', $case->value),
            );
        }
    }

    #[Test]
    public function every_validsign_case_has_a_dutch_translation(): void
    {
        $translator = new EventTranslator($this->app->make('translator'));

        foreach (EventType::cases() as $case) {
            $label = $translator->label($case, 'nl');
            self::assertNotSame(
                $case->value,
                $label,
                sprintf('Missing Dutch translation for %s', $case->value),
            );
        }
    }
}
