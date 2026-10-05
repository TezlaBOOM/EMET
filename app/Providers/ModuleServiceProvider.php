<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ModuleManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, function () {
            return new ModuleManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(ModuleManager $moduleManager): void
    {
        foreach ($moduleManager->all() as $slug => $module) {
            $modulePath = $module['_path'] ?? null;
            if (! $modulePath || ! File::isDirectory($modulePath)) {
                continue;
            }

            // Ładowanie tras modułu
            $routesPath = $modulePath . '/routes/web.php';
            if (File::exists($routesPath)) {
                $this->loadRoutesFrom($routesPath);
            }

            // Ładowanie widoków modułu
            $viewsPath = $modulePath . '/resources/views';
            if (File::isDirectory($viewsPath)) {
                $this->loadViewsFrom($viewsPath, 'module-' . $slug);
            }

            // Ładowanie migracji modułu
            $migrationsPath = $modulePath . '/database/migrations';
            if (File::isDirectory($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}
