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
// Spec: https://docs.anthropic.com/en/docs/agents-and-tools/claude-code
class ClaudeCodeAdapter implements AgentRuntimeAdapter
{
    public function type(): string
    {
        return 'claude_code';
    }

    public function detect(): array
    {
        return [
            'installed' => true,
            'runtime' => 'claude_code',
            'version' => '1.0.0-mock',
            'binary_path' => '/usr/local/bin/claude',
        ];
    }

    public function health(IntegrationInstance $instance): HealthStatus
    {
        return new HealthStatus(true, 'running', 10, ['mock' => true]);
    }

    public function listAgents(IntegrationInstance $instance): array
    {
        return [
            ['id' => 'claude-code-runner', 'name' => 'Claude Code Developer', 'status' => 'ready'],
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
            output: "Claude Code terminal execution for: " . $input->prompt,
            metadata: ['agent_id' => $agent->id, 'runtime' => 'claude_code']
        );
    }

    public function streamEvents(RunHandle $handle): iterable
    {
        yield ['event' => 'code_analysis_started'];
        yield ['event' => 'diff_applied', 'files' => 2];
        yield ['event' => 'task_completed', 'output' => $handle->output];
    }

    public function capabilities(): array
    {
        return [
            'terminal_execution' => true,
            'code_editing' => true,
            'git_integration' => true,
        ];
    }

    public function provisionSpec(ProvisionRequest $request): ProvisionSpec
    {
        $slug = Str::slug($request->name);
        $serviceName = "agenthub-claude@{$slug}";
        $workDir = "/opt/agenthub/instances/claude/{$slug}";

        return new ProvisionSpec(
            serviceName: $serviceName,
            workDir: $workDir,
            environment: ['CLAUDE_INSTANCE' => $slug],
            renderedFiles: ["{$workDir}/claude.json" => "{ \"instance\": \"{$slug}\" }"],
            startCommand: "claude-code-daemon --workspace {$workDir}"
        );
    }

    public function configureInstance(IntegrationInstance $instance, array $config): void
    {
        $instance->update(['config' => array_merge($instance->config ?? [], $config)]);
    }

    public function backupPaths(IntegrationInstance $instance): array
    {
        return ["/opt/agenthub/instances/claude/{$instance->slug}"];
    }
}
