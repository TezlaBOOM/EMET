<?php

namespace App\Providers;

use App\Contracts\Chat\GroupChatOrchestratorInterface;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Memory\VectorStoreInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Services\Chat\GroupChatOrchestrator;
use App\Services\LlmGateway\LlmGateway;
use App\Services\MemoryService\PgVectorStore;
use App\Services\MemoryService\QdrantVectorStore;
use App\Services\Scenarios\ScenarioEngine;
use App\Services\TelemetryCollector\TelemetryCollectorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LlmGatewayInterface::class,
            LlmGateway::class
        );

        $this->app->singleton(
            TelemetryCollectorInterface::class,
            TelemetryCollectorService::class
        );

        $this->app->singleton(VectorStoreInterface::class, function () {
            $driver = config('agenthub.memory.default_driver', 'qdrant');

            return match ($driver) {
                'pgvector' => new PgVectorStore,
                default => new QdrantVectorStore,
            };
        });

        $this->app->singleton(
            GroupChatOrchestratorInterface::class,
            GroupChatOrchestrator::class
        );

        $this->app->singleton(
            ScenarioEngineInterface::class,
            ScenarioEngine::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
