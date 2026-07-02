<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel;

use LauLamanApps\DocumentSigner\DocuSign\DocuSignConfig;
use LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider;
use LauLamanApps\DocumentSigner\Laravel\Pdf\LaravelPdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Pdf\BrowsershotPdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignConfig;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Driver-based manager for the SDK's {@see SignatureProvider}.
 *
 * Modelled on Laravel's {@see \Illuminate\Support\Manager}, but typed: every
 * resolved driver is guaranteed to implement {@see SignatureProvider}, so
 * static analysis and IDE completion work end-to-end.
 *
 * @method \LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt send(\LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope $envelope)
 * @method \LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus getStatus(string $providerEnvelopeId)
 * @method string downloadSigned(string $providerEnvelopeId)
 * @method \SplFileInfo downloadSignedDocument(string $providerEnvelopeId, string $documentId)
 * @method void   cancel(string $providerEnvelopeId, ?string $reason = null)
 */
class DocumentSignerManager
{
    /** @var array<string, SignatureProvider> */
    private array $drivers = [];

    /** @var array<string, \Closure(Container, array<string, mixed>): SignatureProvider> */
    private array $customCreators = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function driver(?string $name = null): SignatureProvider
    {
        $name ??= $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    /**
     * Register a custom driver factory (used for tests, third-party providers).
     *
     * @param \Closure(Container, array<string, mixed>): SignatureProvider $factory
     */
    public function extend(string $name, \Closure $factory): self
    {
        $this->customCreators[$name] = $factory;
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * Replace (or pre-seed) a resolved driver instance. Useful for tests.
     */
    public function set(string $name, SignatureProvider $provider): self
    {
        $this->drivers[$name] = $provider;

        return $this;
    }

    public function forgetDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }

    public function getDefaultDriver(): string
    {
        $explicit = $this->config('document-signer.default');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $configured = $this->configuredDrivers();

        return match (count($configured)) {
            1 => $configured[0],
            0 => throw new InvalidArgumentException(
                'No document-signer driver is configured. Set at least one driver credential '
                . '(VALIDSIGN_API_KEY, DOCUSIGN_INTEGRATION_KEY, ...) or set DOCUMENT_SIGNER_DRIVER explicitly.'
            ),
            default => throw new InvalidArgumentException(sprintf(
                'Multiple document-signer drivers are configured (%s). '
                . 'Set DOCUMENT_SIGNER_DRIVER to pick one.',
                implode(', ', $configured),
            )),
        };
    }

    /**
     * Names of the built-in drivers whose primary credential is present in config.
     *
     * @return list<string>
     */
    public function configuredDrivers(): array
    {
        $primaryCredentials = [
            'docusign'  => 'integration_key',
            'validsign' => 'api_key',
        ];

        $configured = [];
        foreach ($primaryCredentials as $driver => $credentialKey) {
            $value = $this->config("document-signer.drivers.{$driver}.{$credentialKey}");
            if (is_string($value) && $value !== '') {
                $configured[] = $driver;
            }
        }

        return $configured;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->driver()->$method(...$arguments);
    }

    private function resolve(string $name): SignatureProvider
    {
        $config = $this->driverConfig($name);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->container, $config);
        }

        return match ($name) {
            'validsign' => $this->createValidSignDriver($config),
            'docusign'  => $this->createDocuSignDriver($config),
            default     => throw new InvalidArgumentException("Unknown document-signer driver: '{$name}'."),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createValidSignDriver(array $config): SignatureProvider
    {
        if (!class_exists(ValidSignProvider::class)) {
            throw new InvalidArgumentException(
                'The validsign driver requires documentsigner/validsign. Install it with: composer require documentsigner/validsign'
            );
        }

        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'ValidSign API key missing. Set VALIDSIGN_API_KEY or document-signer.drivers.validsign.api_key.'
            );
        }

        return new ValidSignProvider(
            new ValidSignConfig(
                apiKey:               $apiKey,
                baseUrl:              (string) ($config['base_url'] ?? 'https://my.validsign.nl/api'),
                defaultLanguage:      (string) ($config['default_language'] ?? 'nl'),
                timeoutSeconds:       (int)    ($config['timeout'] ?? 15),
                uploadTimeoutSeconds: (int)    ($config['upload_timeout'] ?? 60),
            ),
            pdfRenderer: $this->resolvePdfRenderer(),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDocuSignDriver(array $config): SignatureProvider
    {
        if (!class_exists(DocuSignProvider::class)) {
            throw new InvalidArgumentException(
                'The docusign driver requires documentsigner/docusign. Install it with: composer require documentsigner/docusign'
            );
        }

        $privateKey = $this->resolvePrivateKey($config);

        return new DocuSignProvider(
            new DocuSignConfig(
                integrationKey:        (string) ($config['integration_key'] ?? ''),
                userId:                (string) ($config['user_id'] ?? ''),
                accountId:             (string) ($config['account_id'] ?? ''),
                privateKey:            $privateKey,
                oauthBaseUrl:          (string) ($config['oauth_base_url'] ?? 'account-d.docusign.com'),
                apiBaseUrl:            (string) ($config['api_base_url'] ?? 'https://demo.docusign.net/restapi'),
                scopes:                (string) ($config['scopes'] ?? 'signature impersonation'),
                accessTokenTtlSeconds: (int)    ($config['access_token_ttl'] ?? 3600),
                timeoutSeconds:        (int)    ($config['timeout'] ?? 15),
                uploadTimeoutSeconds:  (int)    ($config['upload_timeout'] ?? 60),
            ),
            pdfRenderer: $this->resolvePdfRenderer(),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolvePrivateKey(array $config): string
    {
        $path = $config['private_key_path'] ?? null;
        if (is_string($path) && $path !== '') {
            if (!is_readable($path)) {
                throw new InvalidArgumentException("DocuSign private key file is not readable: '{$path}'.");
            }
            return (string) file_get_contents($path);
        }

        $inline = $config['private_key'] ?? null;
        if (is_string($inline) && $inline !== '') {
            return $inline;
        }

        throw new InvalidArgumentException(
            'DocuSign private key missing. Set DOCUSIGN_PRIVATE_KEY_PATH or DOCUSIGN_PRIVATE_KEY.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function driverConfig(string $name): array
    {
        $config = $this->config("document-signer.drivers.{$name}");

        if (!is_array($config)) {
            throw new InvalidArgumentException("No configuration found for document-signer driver '{$name}'.");
        }

        return $config;
    }

    private function config(string $key): mixed
    {
        /** @var \Illuminate\Contracts\Config\Repository $repo */
        $repo = $this->container->make('config');

        return $repo->get($key);
    }

    /**
     * Decide which {@see PdfRenderer} every driver should use.
     *
     * Resolution order:
     *  1. A binding for the {@see PdfRenderer} interface in the container — when present
     *     the caller has fully replaced the renderer, including any constructor wiring.
     *  2. The `document-signer.pdf.renderer` config value, which selects between the
     *     two built-in renderers (`browsershot`, `laravel-pdf`). Default: `browsershot`.
     */
    private function resolvePdfRenderer(): PdfRenderer
    {
        if ($this->container->bound(PdfRenderer::class)) {
            $bound = $this->container->make(PdfRenderer::class);
            if (!$bound instanceof PdfRenderer) {
                throw new InvalidArgumentException(sprintf(
                    'Binding for %s must implement %s.',
                    PdfRenderer::class,
                    PdfRenderer::class,
                ));
            }
            return $bound;
        }

        $choice = $this->config('document-signer.pdf.renderer');
        $choice = is_string($choice) && $choice !== '' ? $choice : 'browsershot';

        return match ($choice) {
            'browsershot' => $this->createBrowsershotRenderer(),
            'laravel-pdf' => $this->createLaravelPdfRenderer(),
            default       => throw new InvalidArgumentException(
                "Unknown document-signer PDF renderer: '{$choice}'. "
                . "Expected 'browsershot' or 'laravel-pdf'."
            ),
        };
    }

    private function createBrowsershotRenderer(): PdfRenderer
    {
        if (!class_exists(\Spatie\Browsershot\Browsershot::class)) {
            throw new InvalidArgumentException(
                'The browsershot renderer requires spatie/browsershot. '
                . 'Install it with: composer require spatie/browsershot'
            );
        }

        return new BrowsershotPdfRenderer();
    }

    private function createLaravelPdfRenderer(): PdfRenderer
    {
        if (!class_exists(\Spatie\LaravelPdf\Facades\Pdf::class)) {
            throw new InvalidArgumentException(
                'The laravel-pdf renderer requires spatie/laravel-pdf. '
                . 'Install it with: composer require spatie/laravel-pdf'
            );
        }

        return new LaravelPdfRenderer();
    }
}
