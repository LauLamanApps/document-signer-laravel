<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel;

use LauLamanApps\DocumentSigner\Laravel\Http\Controllers\WebhookController;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DocumentSignerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/document-signer.php', 'document-signer');

        $this->app->singleton(DocumentSignerManager::class, static function (Application $app): DocumentSignerManager {
            return new DocumentSignerManager($app);
        });

        $this->app->alias(DocumentSignerManager::class, 'document-signer');
    }

    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . '/../config/document-signer.php' => $this->configPath('document-signer.php')],
            'document-signer-config',
        );

        Blade::anonymousComponentPath(
            __DIR__ . '/../resources/views/components',
            'document-signer',
        );

        $this->registerWebhookRoutes();
    }

    private function registerWebhookRoutes(): void
    {
        $drivers = $this->app->make(DocumentSignerManager::class)->configuredDrivers();

        if ($drivers === []) {
            return;
        }

        $config = $this->app->make('config');
        $prefix = trim((string) $config->get('document-signer.webhooks.prefix', 'document-signer/webhooks'), '/');
        $middleware = (array) $config->get('document-signer.webhooks.middleware', ['api']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name('document-signer.webhooks.')
            ->group(static function () use ($drivers): void {
                foreach ($drivers as $driver) {
                    Route::post($driver, [WebhookController::class, $driver])->name($driver);
                }
            });
    }

    private function configPath(string $file): string
    {
        $base = $this->app instanceof Application && method_exists($this->app, 'configPath')
            ? $this->app->configPath($file)
            : base_path('config/' . $file);

        return $base;
    }
}
