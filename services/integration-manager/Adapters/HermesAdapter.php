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

// TODO: SDK Pending - using mocked driver
// Spec: https://github.com/nousresearch/hermes-agent
class HermesAdapter implements AgentRuntimeAdapter, ContainerAdapterInterface
{
    public function type(): string
    {
        return 'hermes';
    }

    public function detect(): array
    {
        return [
            'installed' => true,
            'runtime' => 'hermes',
            'version' => '1.2.0-mock',
            'binary_path' => '/usr/local/bin/hermes',
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
                $res = Http::timeout(2)->get("{$instance->endpoint_url}/health");
                $latency = (int) round((microtime(true) - $start) * 1000);
                if ($res->successful()) {
                    return new HealthStatus(true, 'running', $latency, $res->json() ?? []);
                }
            } catch (\Exception) {
                // mock fallback
            }
        }

        return new HealthStatus(true, 'running', 8, ['mock' => true]);
    }

    public function listAgents(IntegrationInstance $instance): array
    {
        return [
            ['id' => 'hermes-core', 'name' => 'Hermes Core Executor', 'status' => 'ready'],
        ];
    }

    public function syncAgent(Agent $agent, IntegrationInstance $instance): void
    {
        // Mock synchronizacji agenta z instancją Hermes
    }

    public function runTask(Agent $agent, TaskInput $input): RunHandle
    {
        $runId = (string) Str::uuid();

        return new RunHandle(
            runId: $runId,
            status: 'completed',
            output: 'Hermes autonomous execution output for: '.$input->prompt,
            metadata: ['agent_id' => $agent->id, 'runtime' => 'hermes']
        );
    }

    public function streamEvents(RunHandle $handle): iterable
    {
        yield ['event' => 'task_started', 'run_id' => $handle->runId];
        yield ['event' => 'reasoning_step', 'content' => 'Analiza zadania przez model Hermes...'];
        yield ['event' => 'tool_call', 'tool' => 'bash_exec', 'args' => ['ls -la']];
        yield ['event' => 'task_completed', 'output' => $handle->output];
    }

    public function capabilities(): array
    {
        return [
            'streaming' => true,
            'tools' => true,
            'long_running_jobs' => true,
            'systemd_native' => true,
            'docker_native' => true,
        ];
    }

    public function provisionSpec(ProvisionRequest $request): ProvisionSpec
    {
        $slug = Str::slug($request->name);
        $port = $request->port ?: 8080;
        $serviceName = "agenthub-hermes@{$slug}";
        $workDir = "/opt/agenthub/instances/hermes/{$slug}";

        return new ProvisionSpec(
            serviceName: $serviceName,
            workDir: $workDir,
            environment: [
                'HERMES_PORT' => (string) $port,
                'HERMES_AGENT_ID' => $slug,
                'AGRNTHUB_GATEWAY_URL' => config('app.url', 'http://localhost').'/api/v1/gateway',
            ],
            renderedFiles: [
                "{$workDir}/config.yaml" => "port: {$port}\nname: {$request->name}\nmode: autonomous\n",
            ],
            startCommand: "hermes-agent --config {$workDir}/config.yaml"
        );
    }

    public function configureInstance(IntegrationInstance $instance, array $config): void
    {
        $instance->update(['config' => array_merge($instance->config ?? [], $config)]);
    }

    public function backupPaths(IntegrationInstance $instance): array
    {
        return [
            "/opt/agenthub/instances/hermes/{$instance->slug}",
        ];
    }

    // --- ContainerAdapterInterface Implementation ---

    public function containerSignatures(): array
    {
        return [
            'images' => ['nousresearch/hermes-agent*', 'hermes-agent*'],
            'labels' => ['agenthub.runtime=hermes'],
            'ports' => [8080, 8000],
        ];
    }

    public function inspectContainer(string $containerId): array
    {
        return [
            'name' => "hermes-{$containerId}",
            'image' => 'nousresearch/hermes-agent:latest',
            'status' => 'running',
            'ports' => [8080],
            'detected_type' => 'hermes',
            'is_compatible' => true,
            'details' => [
                'version' => '1.2.0',
                'models_loaded' => ['hermes-3-llama-3.1-8b'],
            ],
        ];
    }

    public function configWritablePaths(): array
    {
        return [
            '/opt/agenthub/instances/hermes',
            '/etc/hermes',
        ];
    }

    public function execAllowlist(): array
    {
        return [
            'hermes --check',
            'hermes --version',
            'hermes status',
            'hermes reload',
            'hermes model pull *',
        ];
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'port' => ['type' => 'integer', 'default' => 8080],
                'model' => ['type' => 'string', 'default' => 'hermes-3-llama-3.1-8b'],
                'autonomous_mode' => ['type' => 'boolean', 'default' => true],
            ],
            'required' => ['model'],
        ];
    }

    public function configureContainer(string $containerId, array $config): array
    {
        $diff = [
            'before' => ['model' => 'default'],
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
        $log = "Applying Hermes auto-configuration steps for container {$containerId}...\n";
        foreach ($profileSteps as $step) {
            $name = $step['name'] ?? 'Step';
            $log .= "- Executed: {$name}\n";
        }
        $log .= "Hermes container {$containerId} configured successfully.";

        return [
            'success' => true,
            'log' => $log,
            'error' => null,
        ];
    }

    public function listContainerModels(string $containerId): array
    {
        return [
            ['id' => 'hermes-3-llama-3.1-8b', 'name' => 'Hermes 3 (Llama 3.1 8B)', 'size_bytes' => 4500000000],
            ['id' => 'hermes-3-llama-3.1-70b', 'name' => 'Hermes 3 (Llama 3.1 70B)', 'size_bytes' => 38000000000],
        ];
    }
}
