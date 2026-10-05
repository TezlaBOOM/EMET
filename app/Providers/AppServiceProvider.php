<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Contracts\Llm\LlmGatewayInterface::class,
            \App\Services\LlmGateway\LlmGateway::class
        );

        $this->app->singleton(
            \App\Contracts\Telemetry\TelemetryCollectorInterface::class,
            \App\Services\TelemetryCollector\TelemetryCollectorService::class
        );

        $this->app->singleton(\App\Contracts\Memory\VectorStoreInterface::class, function () {
            $driver = config('agenthub.memory.default_driver', 'qdrant');
            return match ($driver) {
                'pgvector' => new \App\Services\MemoryService\PgVectorStore(),
                default => new \App\Services\MemoryService\QdrantVectorStore(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
