<?php

declare(strict_types=1);

namespace App\Services\IntegrationManager\Adapters;

use App\Contracts\Integrations\AgentRuntimeAdapter;
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
class HermesAdapter implements AgentRuntimeAdapter
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
            output: "Hermes autonomous execution output for: " . $input->prompt,
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
                'AGRNTHUB_GATEWAY_URL' => config('app.url', 'http://localhost') . '/api/v1/gateway',
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
}
