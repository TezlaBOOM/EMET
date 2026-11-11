<?php

namespace App\Providers;

use App\Contracts\Chat\GroupChatOrchestratorInterface;
use App\Contracts\Integrations\McpHubInterface;
use App\Contracts\Llm\AiGuardrailsInterface;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Llm\ModelManagementInterface;
use App\Contracts\Llm\SemanticCacheInterface;
use App\Contracts\Memory\HybridRagInterface;
use App\Contracts\Memory\VectorStoreInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Scenarios\ScenarioSchedulerInterface;
use App\Contracts\Skills\SkillLifecycleInterface;
use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Services\Chat\GroupChatOrchestrator;
use App\Services\Integrations\McpHubService;
use App\Services\LlmGateway\AiGuardrailsService;
use App\Services\LlmGateway\LlmGateway;
use App\Services\LlmGateway\ModelManagementService;
use App\Services\LlmGateway\SemanticCacheService;
use App\Services\MemoryService\HybridRagService;
use App\Services\MemoryService\MockVectorStore;
use App\Services\MemoryService\PgVectorStore;
use App\Services\MemoryService\QdrantVectorStore;
use App\Services\Scenarios\ScenarioEngine;
use App\Services\Scenarios\ScenarioSchedulerService;
use App\Services\Skills\SkillLifecycleService;
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
            SemanticCacheInterface::class,
            SemanticCacheService::class
        );

        $this->app->singleton(
            AiGuardrailsInterface::class,
            AiGuardrailsService::class
        );

        $this->app->singleton(
            LlmGatewayInterface::class,
            LlmGateway::class
        );

        $this->app->singleton(
            ModelManagementInterface::class,
            ModelManagementService::class
        );

        $this->app->singleton(
            TelemetryCollectorInterface::class,
            TelemetryCollectorService::class
        );

        $this->app->singleton(VectorStoreInterface::class, function () {
            $driver = config('agenthub.memory.default_driver', 'qdrant');

            return match ($driver) {
                'pgvector' => new PgVectorStore,
                'mock', 'null' => new MockVectorStore,
                default => new QdrantVectorStore,
            };
        });

        $this->app->singleton(
            HybridRagInterface::class,
            HybridRagService::class
        );

        $this->app->singleton(
            McpHubInterface::class,
            McpHubService::class
        );

        $this->app->singleton(
            GroupChatOrchestratorInterface::class,
            GroupChatOrchestrator::class
        );

        $this->app->singleton(
            ScenarioEngineInterface::class,
            ScenarioEngine::class
        );

        $this->app->singleton(
            SkillLifecycleInterface::class,
            SkillLifecycleService::class
        );

        $this->app->singleton(
            ScenarioSchedulerInterface::class,
            ScenarioSchedulerService::class
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
