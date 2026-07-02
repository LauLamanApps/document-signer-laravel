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

    public function test_no_webhook_routes_when_no_secrets_are_configured(): void
    {
        $this->envOverrides = [
            'document-signer.webhooks.docusign.hmac_secret'      => null,
            'document-signer.webhooks.validsign.callback_secret' => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
    }

    public function test_only_validsign_webhook_when_only_validsign_secret_is_set(): void
    {
        $this->envOverrides = [
            'document-signer.webhooks.validsign.callback_secret' => 'vs-secret',
            'document-signer.webhooks.docusign.hmac_secret'      => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.validsign', $routes);
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_only_docusign_webhook_when_only_docusign_secret_is_set(): void
    {
        $this->envOverrides = [
            'document-signer.webhooks.docusign.hmac_secret'      => 'ds-secret',
            'document-signer.webhooks.validsign.callback_secret' => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.docusign', $routes);
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
    }

    public function test_both_webhooks_when_both_secrets_are_set(): void
    {
        $this->envOverrides = [
            'document-signer.webhooks.validsign.callback_secret' => 'vs-secret',
            'document-signer.webhooks.docusign.hmac_secret'      => 'ds-secret',
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertContains('document-signer.webhooks.validsign', $routes);
        self::assertContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_driver_credentials_alone_do_not_enable_webhooks(): void
    {
        // Setting the API key doesn't imply we want to receive webhooks —
        // only the webhook secret does. Guards against a regression that
        // would re-couple the two.
        $this->envOverrides = [
            'document-signer.drivers.validsign.api_key'          => 'k',
            'document-signer.drivers.docusign.integration_key'   => 'i',
            'document-signer.webhooks.validsign.callback_secret' => null,
            'document-signer.webhooks.docusign.hmac_secret'      => null,
        ];
        $this->refreshApplication();

        $routes = $this->routeNames();
        self::assertNotContains('document-signer.webhooks.validsign', $routes);
        self::assertNotContains('document-signer.webhooks.docusign', $routes);
    }

    public function test_empty_string_secret_is_treated_as_absent(): void
    {
        // Users often clear the .env value to ''; that should behave the
        // same as never setting it at all.
        $this->envOverrides = [
            'document-signer.webhooks.validsign.callback_secret' => '',
        ];
        $this->refreshApplication();

        self::assertNotContains('document-signer.webhooks.validsign', $this->routeNames());
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
