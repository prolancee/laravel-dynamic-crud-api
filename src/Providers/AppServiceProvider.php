<?php

namespace PROLANCEE\DYNAMIC\CRUD\Api\Providers;

use Illuminate\Support\ServiceProvider;
use PROLANCEE\DYNAMIC\CRUD\Api\Console\Install;
use PROLANCEE\DYNAMIC\CRUD\Api\Providers\RouteServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if (! $this->app->providerIsLoaded(RouteServiceProvider::class)) {
            $this->app->register(RouteServiceProvider::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->publishConfig();

        $this->commands([
            Install::class,
        ]);
    }

    /**
     * Publishes config files.
     */
    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => $this->configPath('prolancee/dynamic.crud.api.php'),
        ], 'prolancee:dynamic-crud-api:config');
    }

    /**
     * Resolve the target configuration file path for publishing.
     */
    private function configPath(string $file = ''): string
    {
        return base_path('config' . ($file ? '/' . $file : ''));
    }
}
