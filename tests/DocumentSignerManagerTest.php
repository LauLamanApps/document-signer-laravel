<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Tests;

use LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider;
use LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt;
use LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentSignerManagerTest extends TestCase
{
    private static ?string $rsaPem = null;

    public static function setUpBeforeClass(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource !== false) {
            openssl_pkey_export($resource, $pem);
            self::$rsaPem = $pem;
        }
    }

    #[Test]
    public function it_resolves_the_default_driver(): void
    {
        $manager = $this->makeManager([
            'default' => 'validsign',
            'drivers' => [
                'validsign' => ['api_key' => 'k'],
            ],
        ]);

        self::assertInstanceOf(ValidSignProvider::class, $manager->driver());
    }

    #[Test]
    public function it_resolves_named_drivers(): void
    {
        if (self::$rsaPem === null) {
            self::markTestSkipped('openssl not available');
        }

        $manager = $this->makeManager([
            'default' => 'validsign',
            'drivers' => [
                'validsign' => ['api_key' => 'k'],
                'docusign'  => [
                    'integration_key' => 'i', 'user_id' => 'u', 'account_id' => 'a',
                    'private_key' => self::$rsaPem,
                ],
            ],
        ]);

        self::assertInstanceOf(ValidSignProvider::class, $manager->driver('validsign'));
        self::assertInstanceOf(DocuSignProvider::class, $manager->driver('docusign'));
    }

    #[Test]
    public function it_caches_resolved_drivers(): void
    {
        $manager = $this->makeManager([
            'default' => 'validsign',
            'drivers' => ['validsign' => ['api_key' => 'k']],
        ]);

        self::assertSame($manager->driver(), $manager->driver());
    }

    #[Test]
    public function it_lets_callers_seed_drivers_for_tests(): void
    {
        $fake = $this->fakeProvider();
        $manager = $this->makeManager([
            'default' => 'validsign',
            'drivers' => ['validsign' => ['api_key' => 'k']],
        ]);

        $manager->set('validsign', $fake);

        self::assertSame($fake, $manager->driver('validsign'));
    }

    #[Test]
    public function it_supports_extending_with_a_custom_driver_factory(): void
    {
        $fake = $this->fakeProvider();
        $manager = $this->makeManager([
            'default' => 'hellosign',
            'drivers' => ['hellosign' => ['anything' => true]],
        ]);

        $manager->extend('hellosign', static fn () => $fake);

        self::assertSame($fake, $manager->driver('hellosign'));
    }

    #[Test]
    public function it_throws_a_clear_error_when_validsign_api_key_is_missing(): void
    {
        $manager = $this->makeManager([
            'default' => 'validsign',
            'drivers' => ['validsign' => ['api_key' => '']],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ValidSign API key missing');

        $manager->driver('validsign');
    }

    #[Test]
    public function it_throws_when_no_drivers_are_configured_and_no_default_is_set(): void
    {
        $manager = $this->makeManager(['drivers' => []]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No document-signer driver is configured');

        $manager->driver();
    }

    #[Test]
    public function it_auto_selects_the_sole_configured_driver_when_no_default_is_set(): void
    {
        $manager = $this->makeManager([
            'drivers' => [
                'validsign' => ['api_key' => 'k'],
                'docusign'  => ['integration_key' => null],
            ],
        ]);

        self::assertSame('validsign', $manager->getDefaultDriver());
        self::assertInstanceOf(ValidSignProvider::class, $manager->driver());
    }

    #[Test]
    public function it_auto_selects_docusign_when_only_docusign_is_configured(): void
    {
        if (self::$rsaPem === null) {
            self::markTestSkipped('openssl not available');
        }

        $manager = $this->makeManager([
            'drivers' => [
                'validsign' => ['api_key' => null],
                'docusign'  => [
                    'integration_key' => 'i', 'user_id' => 'u', 'account_id' => 'a',
                    'private_key' => self::$rsaPem,
                ],
            ],
        ]);

        self::assertSame('docusign', $manager->getDefaultDriver());
        self::assertInstanceOf(DocuSignProvider::class, $manager->driver());
    }

    #[Test]
    public function it_throws_a_multi_driver_hint_when_more_than_one_is_configured_and_no_default_is_set(): void
    {
        $manager = $this->makeManager([
            'drivers' => [
                'validsign' => ['api_key' => 'k'],
                'docusign'  => ['integration_key' => 'i'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple document-signer drivers are configured (docusign, validsign)');

        $manager->driver();
    }

    #[Test]
    public function explicit_default_wins_over_auto_selection(): void
    {
        if (self::$rsaPem === null) {
            self::markTestSkipped('openssl not available');
        }

        $manager = $this->makeManager([
            'default' => 'docusign',
            'drivers' => [
                'validsign' => ['api_key' => 'k'],
                'docusign'  => [
                    'integration_key' => 'i', 'user_id' => 'u', 'account_id' => 'a',
                    'private_key' => self::$rsaPem,
                ],
            ],
        ]);

        self::assertInstanceOf(DocuSignProvider::class, $manager->driver());
    }

    #[Test]
    public function it_throws_on_unknown_driver(): void
    {
        $manager = $this->makeManager([
            'default' => 'wat',
            'drivers' => ['wat' => []],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown document-signer driver: 'wat'");

        $manager->driver('wat');
    }

    /**
     * @param array<string, mixed> $documentSignerConfig
     */
    private function makeManager(array $documentSignerConfig): DocumentSignerManager
    {
        $container = new Container();
        $container->instance('config', new Repository(['document-signer' => $documentSignerConfig]));

        return new DocumentSignerManager($container);
    }

    private function fakeProvider(): SignatureProvider
    {
        return new class implements SignatureProvider {
            public function send($envelope): EnvelopeReceipt
            {
                return new EnvelopeReceipt(provider: 'fake', providerEnvelopeId: 'x', status: EnvelopeStatus::Sent);
            }
            public function getStatus(string $providerEnvelopeId): EnvelopeStatus { return EnvelopeStatus::Completed; }
            public function downloadSigned(string $providerEnvelopeId): \SplFileInfo { return new \SplFileInfo('/dev/null'); }
            public function downloadSignedDocument(string $providerEnvelopeId, string $documentId): \SplFileInfo { return new \SplFileInfo('/dev/null'); }
            public function hasAuditTrail(): bool { return true; }
            public function downloadAudit(string $providerEnvelopeId): \SplFileInfo { return new \SplFileInfo('/dev/null'); }
            public function getFieldValues(string $providerEnvelopeId): array { return []; }
            public function cancel(string $providerEnvelopeId, ?string $reason = null): void {}
        };
    }
}
