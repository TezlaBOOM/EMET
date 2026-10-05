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
use Illuminate\Support\Str;

// TODO: SDK Pending - using mocked driver
// Spec: https://github.com/openai/codex
class CodexAdapter implements AgentRuntimeAdapter
{
    public function type(): string
    {
        return 'codex';
    }

    public function detect(): array
    {
        return [
            'installed' => true,
            'runtime' => 'codex',
            'version' => '2.1.0-mock',
            'binary_path' => '/usr/local/bin/codex',
        ];
    }

    public function health(IntegrationInstance $instance): HealthStatus
    {
        return new HealthStatus(true, 'running', 15, ['mock' => true]);
    }

    public function listAgents(IntegrationInstance $instance): array
    {
        return [
            ['id' => 'codex-cli', 'name' => 'Codex CLI Agent', 'status' => 'ready'],
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
            output: "Codex code synthesis output for: " . $input->prompt,
            metadata: ['agent_id' => $agent->id, 'runtime' => 'codex']
        );
    }

    public function streamEvents(RunHandle $handle): iterable
    {
        yield ['event' => 'synthesis_started'];
        yield ['event' => 'code_generated'];
        yield ['event' => 'task_completed', 'output' => $handle->output];
    }

    public function capabilities(): array
    {
        return [
            'code_generation' => true,
            'autocomplete' => true,
        ];
    }

    public function provisionSpec(ProvisionRequest $request): ProvisionSpec
    {
        $slug = Str::slug($request->name);
        $serviceName = "agenthub-codex@{$slug}";
        $workDir = "/opt/agenthub/instances/codex/{$slug}";

        return new ProvisionSpec(
            serviceName: $serviceName,
            workDir: $workDir,
            environment: ['CODEX_INSTANCE' => $slug],
            renderedFiles: ["{$workDir}/codex.conf" => "instance={$slug}\n"],
            startCommand: "codex-daemon --conf {$workDir}/codex.conf"
        );
    }

    public function configureInstance(IntegrationInstance $instance, array $config): void
    {
        $instance->update(['config' => array_merge($instance->config ?? [], $config)]);
    }

    public function backupPaths(IntegrationInstance $instance): array
    {
        return ["/opt/agenthub/instances/codex/{$instance->slug}"];
    }
}
