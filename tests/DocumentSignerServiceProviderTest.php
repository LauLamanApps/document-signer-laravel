<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Tests;

use LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager;
use LauLamanApps\DocumentSigner\Laravel\DocumentSignerServiceProvider;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase;

final class DocumentSignerServiceProviderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $envOverrides = [];

    protected function getPackageProviders($app): array
    {
        return [DocumentSignerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        foreach ($this->envOverrides as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    public function test_it_binds_the_manager_as_a_singleton(): void
    {
        $a = $this->app->make(DocumentSignerManager::class);
        $b = $this->app->make(DocumentSignerManager::class);

        self::assertSame($a, $b);
        self::assertSame($a, $this->app->make('document-signer'));
    }

    public function test_no_webhook_routes_when_no_drivers_are_configured(): void
    {
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key'    => null,
            'document-signer.drivers.docusign.integration_key' => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
    }

    public function test_only_validsign_webhook_when_only_validsign_configured(): void
    {
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key' => 'k',
            'document-signer.drivers.docusign.integration_key' => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.validsign', $routes);
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_only_docusign_webhook_when_only_docusign_configured(): void
    {
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key' => null,
            'document-signer.drivers.docusign.integration_key' => 'i',
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.docusign', $routes);
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
    }

    public function test_both_webhooks_when_both_drivers_configured(): void
    {
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key' => 'k',
            'document-signer.drivers.docusign.integration_key' => 'i',
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.validsign', $routes);
        self::assertContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_webhook_routes_are_skipped_when_enabled_is_false_even_with_credentials_set(): void
    {
        $this->envOverrides = [
            'document-signer.webhooks.enabled' => false,
            'document-signer.drivers.validsign.api_key' => 'k',
            'document-signer.drivers.docusign.integration_key' => 'i',
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_webhook_routes_still_register_when_enabled_defaults_to_true(): void
    {
        // No explicit `webhooks.enabled` key — should default to true.
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key' => 'k',
        ];
        $this->refreshApplication();

        self::assertContains('document-signer.webhooks.validsign', $this->routeNames());
    }

    /**
     * @return list<string>
     */
    private function routeNames(): array
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $names = [];
        foreach ($router->getRoutes() as $route) {
            $name = $route->getName();
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }
}
