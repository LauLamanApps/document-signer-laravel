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

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'document-signer');

        $this->publishes(
            [__DIR__ . '/../resources/lang' => $this->langPath('vendor/document-signer')],
            'document-signer-translations',
        );

        $this->registerWebhookRoutes();
    }

    private function registerWebhookRoutes(): void
    {
        $config = $this->app->make('config');

        $drivers = $this->driversWithWebhookSecret($config);
        if ($drivers === []) {
            return;
        }

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

    /**
     * A webhook route is registered per driver iff its signing secret is set.
     * A webhook with no secret would 401 every request anyway, so treating
     * secret-presence as the on/off flag keeps the config surface minimal.
     *
     * @return list<string>
     */
    private function driversWithWebhookSecret(\Illuminate\Contracts\Config\Repository $config): array
    {
        $secretPaths = [
            'docusign'  => 'document-signer.webhooks.docusign.hmac_secret',
            'validsign' => 'document-signer.webhooks.validsign.callback_secret',
        ];

        $out = [];
        foreach ($secretPaths as $driver => $path) {
            $value = $config->get($path);
            if (is_string($value) && $value !== '') {
                $out[] = $driver;
            }
        }
        return $out;
    }

    private function configPath(string $file): string
    {
        $base = $this->app instanceof Application && method_exists($this->app, 'configPath')
            ? $this->app->configPath($file)
            : base_path('config/' . $file);

        return $base;
    }

    private function langPath(string $sub): string
    {
        if ($this->app instanceof Application && method_exists($this->app, 'langPath')) {
            return $this->app->langPath($sub);
        }

        return base_path('lang/' . $sub);
    }
}
