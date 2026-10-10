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
use Illuminate\Support\Str;

// TODO: SDK Pending - using mocked driver
// Spec: https://github.com/openclaw/openclaw
class OpenClawAdapter implements AgentRuntimeAdapter, ContainerAdapterInterface
{
    public function type(): string
    {
        return 'openclaw';
    }

    public function detect(): array
    {
        return [
            'installed' => true,
            'runtime' => 'openclaw',
            'version' => '0.9.4-mock',
            'binary_path' => '/usr/local/bin/openclaw',
        ];
    }

    public function health(IntegrationInstance $instance): HealthStatus
    {
        if ($instance->status === 'stopped') {
            return new HealthStatus(false, 'stopped', 0);
        }

        return new HealthStatus(true, 'running', 12, ['mock' => true]);
    }

    public function listAgents(IntegrationInstance $instance): array
    {
        return [
            ['id' => 'claw-scraper', 'name' => 'OpenClaw Web Extractor', 'status' => 'ready'],
        ];
    }

    public function syncAgent(Agent $agent, IntegrationInstance $instance): void
    {
        // Mock sync
    }

    public function runTask(Agent $agent, TaskInput $input): RunHandle
    {
        $runId = (string) Str::uuid();

        return new RunHandle(
            runId: $runId,
            status: 'completed',
            output: 'OpenClaw web crawler output for: '.$input->prompt,
            metadata: ['agent_id' => $agent->id, 'runtime' => 'openclaw']
        );
    }

    public function streamEvents(RunHandle $handle): iterable
    {
        yield ['event' => 'crawler_started', 'run_id' => $handle->runId];
        yield ['event' => 'dom_extracted', 'urls_visited' => 3];
        yield ['event' => 'task_completed', 'output' => $handle->output];
    }

    public function capabilities(): array
    {
        return [
            'web_scraping' => true,
            'headless_browser' => true,
            'dom_actions' => true,
        ];
    }

    public function provisionSpec(ProvisionRequest $request): ProvisionSpec
    {
        $slug = Str::slug($request->name);
        $port = $request->port ?: 8090;
        $serviceName = "agenthub-openclaw@{$slug}";
        $workDir = "/opt/agenthub/instances/openclaw/{$slug}";

        return new ProvisionSpec(
            serviceName: $serviceName,
            workDir: $workDir,
            environment: [
                'OPENCLAW_PORT' => (string) $port,
                'OPENCLAW_INSTANCE' => $slug,
            ],
            renderedFiles: [
                "{$workDir}/openclaw.env" => "PORT={$port}\nINSTANCE={$slug}\n",
            ],
            startCommand: "openclaw-server --port {$port}"
        );
    }

    public function configureInstance(IntegrationInstance $instance, array $config): void
    {
        $instance->update(['config' => array_merge($instance->config ?? [], $config)]);
    }

    public function backupPaths(IntegrationInstance $instance): array
    {
        return [
            "/opt/agenthub/instances/openclaw/{$instance->slug}",
        ];
    }

    // --- ContainerAdapterInterface Implementation ---

    public function containerSignatures(): array
    {
        return [
            'images' => ['openclaw/openclaw*', 'openclaw*'],
            'labels' => ['agenthub.runtime=openclaw'],
            'ports' => [8090],
        ];
    }

    public function inspectContainer(string $containerId): array
    {
        return [
            'name' => "openclaw-{$containerId}",
            'image' => 'openclaw/openclaw:latest',
            'status' => 'running',
            'ports' => [8090],
            'detected_type' => 'openclaw',
            'is_compatible' => true,
            'details' => [
                'version' => '0.9.4',
                'headless_browser' => 'chromium',
            ],
        ];
    }

    public function configWritablePaths(): array
    {
        return [
            '/opt/agenthub/instances/openclaw',
            '/etc/openclaw',
        ];
    }

    public function execAllowlist(): array
    {
        return [
            'openclaw --status',
            'openclaw --version',
            'openclaw reload',
        ];
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'port' => ['type' => 'integer', 'default' => 8090],
                'headless' => ['type' => 'boolean', 'default' => true],
                'concurrency' => ['type' => 'integer', 'default' => 4],
            ],
            'required' => ['port'],
        ];
    }

    public function configureContainer(string $containerId, array $config): array
    {
        $diff = [
            'before' => ['port' => 8090, 'concurrency' => 2],
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
        $log = "Applying OpenClaw auto-configuration steps for container {$containerId}...\n";
        foreach ($profileSteps as $step) {
            $name = $step['name'] ?? 'Step';
            $log .= "- Executed: {$name}\n";
        }
        $log .= "OpenClaw container {$containerId} configured successfully.";

        return [
            'success' => true,
            'log' => $log,
            'error' => null,
        ];
    }

    public function listContainerModels(string $containerId): array
    {
        return [];
    }
}
