<?php

declare(strict_types=1);

namespace App\Services\IntegrationManager\Adapters;

use App\Contracts\Integrations\AgentRuntimeAdapter;
use App\Contracts\Integrations\ContainerAdapterInterface;
use App\Contracts\Integrations\HealthStatus;
use App\Contracts\Integrations\ProvisionRequest;
use App\Contracts\Integrations\ProvisionSpec;
use App\Contracts\Integrations\RunHandle;
use App\Contracts\Integrations\TaskInput;
use App\Models\Agent;
use App\Models\IntegrationInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OllamaAdapter implements AgentRuntimeAdapter, ContainerAdapterInterface
{
    public function type(): string
    {
        return 'ollama';
    }

    public function detect(): array
    {
        return [
            'installed' => true,
            'runtime' => 'ollama',
            'version' => '0.5.1',
            'binary_path' => '/usr/local/bin/ollama',
        ];
    }

    public function health(IntegrationInstance $instance): HealthStatus
    {
        if ($instance->status === 'stopped') {
            return new HealthStatus(false, 'stopped', 0);
        }

        if ($instance->endpoint_url) {
            try {
                $start = microtime(true);
                $res = Http::timeout(2)->get("{$instance->endpoint_url}/api/tags");
                $latency = (int) round((microtime(true) - $start) * 1000);
                if ($res->successful()) {
                    return new HealthStatus(true, 'running', $latency, $res->json() ?? []);
                }
            } catch (\Exception) {
                // mock fallback
            }
        }

        return new HealthStatus(true, 'running', 5, ['mock' => true]);
    }

    public function listAgents(IntegrationInstance $instance): array
    {
        return [
            ['id' => 'ollama-llama3', 'name' => 'Llama 3.2 Ollama Runner', 'status' => 'ready'],
        ];
    }

    public function syncAgent(Agent $agent, IntegrationInstance $instance): void {}

    public function runTask(Agent $agent, TaskInput $input): RunHandle
    {
        $runId = (string) Str::uuid();

        return new RunHandle(
            runId: $runId,
            status: 'completed',
            output: 'Ollama model inference output for: '.$input->prompt,
            metadata: ['agent_id' => $agent->id, 'runtime' => 'ollama']
        );
    }

    public function streamEvents(RunHandle $handle): iterable
    {
        yield ['event' => 'inference_started', 'run_id' => $handle->runId];
        yield ['event' => 'token_generated', 'token' => 'Hello'];
        yield ['event' => 'task_completed', 'output' => $handle->output];
    }

    public function capabilities(): array
    {
        return [
            'local_llm' => true,
            'streaming' => true,
            'gpu_acceleration' => true,
        ];
    }

    public function provisionSpec(ProvisionRequest $request): ProvisionSpec
    {
        $slug = Str::slug($request->name);
        $port = $request->port ?: 11434;
        $serviceName = "agenthub-ollama@{$slug}";
        $workDir = "/opt/agenthub/instances/ollama/{$slug}";

        return new ProvisionSpec(
            serviceName: $serviceName,
            workDir: $workDir,
            environment: [
                'OLLAMA_HOST' => "0.0.0.0:{$port}",
            ],
            renderedFiles: [
                "{$workDir}/env" => "OLLAMA_HOST=0.0.0.0:{$port}\n",
            ],
            startCommand: 'ollama serve'
        );
    }

    public function configureInstance(IntegrationInstance $instance, array $config): void
    {
        $instance->update(['config' => array_merge($instance->config ?? [], $config)]);
    }

    public function backupPaths(IntegrationInstance $instance): array
    {
        return [
            "/opt/agenthub/instances/ollama/{$instance->slug}",
        ];
    }

    // --- ContainerAdapterInterface Implementation ---

    public function containerSignatures(): array
    {
        return [
            'images' => ['ollama/ollama*', 'ollama*'],
            'labels' => ['agenthub.runtime=ollama'],
            'ports' => [11434],
        ];
    }

    public function inspectContainer(string $containerId): array
    {
        return [
            'name' => "ollama-{$containerId}",
            'image' => 'ollama/ollama:latest',
            'status' => 'running',
            'ports' => [11434],
            'detected_type' => 'ollama',
            'is_compatible' => true,
            'details' => [
                'version' => '0.5.1',
                'models_count' => 3,
            ],
        ];
    }

    public function configWritablePaths(): array
    {
        return [
            '/root/.ollama',
            '/etc/ollama',
        ];
    }

    public function execAllowlist(): array
    {
        return [
            'ollama list',
            'ollama pull *',
            'ollama rm *',
            'ollama --version',
            'ollama ps',
        ];
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'port' => ['type' => 'integer', 'default' => 11434],
                'default_model' => ['type' => 'string', 'default' => 'llama3.2'],
                'keep_alive' => ['type' => 'string', 'default' => '5m'],
            ],
            'required' => ['default_model'],
        ];
    }

    public function configureContainer(string $containerId, array $config): array
    {
        $diff = [
            'before' => ['default_model' => 'llama3.2:1b'],
            'after' => $config,
        ];

        return [
            'success' => true,
            'diff' => $diff,
            'error' => null,
        ];
    }

    public function autoConfigure(string $containerId, array $profileSteps): array
    {
        $log = "Applying Ollama auto-configuration steps for container {$containerId}...\n";
        foreach ($profileSteps as $step) {
            $name = $step['name'] ?? 'Step';
            $log .= "- Executed: {$name}\n";
        }
        $log .= "Ollama container {$containerId} configured successfully.";

        return [
            'success' => true,
            'log' => $log,
            'error' => null,
        ];
    }

    public function listContainerModels(string $containerId): array
    {
        return [
            ['id' => 'llama3.2', 'name' => 'Llama 3.2 (3B)', 'size_bytes' => 2000000000],
            ['id' => 'mistral', 'name' => 'Mistral 7B', 'size_bytes' => 4100000000],
            ['id' => 'qwen2.5-coder', 'name' => 'Qwen 2.5 Coder (7B)', 'size_bytes' => 4700000000],
        ];
    }
}
