<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\Agent;
use App\Models\IntegrationInstance;

/**
 * Kontrakt bazowy dla adapterów runtime'ów agentów zewnętrznych (Hermes, OpenClaw, Claude Code itp.)
 */
interface AgentRuntimeAdapter
{
    /** Identyfikator typu integracji (np. 'hermes', 'openclaw') */
    public function type(): string;

    /** Wykrywanie instalacji na hoście */
    public function detect(): array;

    /** Sprawdzenie stanu zdrowia instancji lub usługi */
    public function health(IntegrationInstance $instance): HealthStatus;

    /** Pobranie listy agentów z instancji */
    public function listAgents(IntegrationInstance $instance): array;

    /** Synchronizacja konfiguracji agenta z platformy do instancji */
    public function syncAgent(Agent $agent, IntegrationInstance $instance): void;

    /** Uruchomienie zadania przez agenta */
    public function runTask(Agent $agent, TaskInput $input): RunHandle;

    /** Strumień zdarzeń i telemetrii z działającego agenta */
    public function streamEvents(RunHandle $handle): iterable;

    /** Zwraca deklarację możliwości runtime'u (streaming, memory, tool_calling itp.) */
    public function capabilities(): array;

    /** Zwraca specyfikację instalacji/provisioningu nowej instancji */
    public function provisionSpec(ProvisionRequest $request): ProvisionSpec;

    /** Konfiguracja uruchomionej instancji */
    public function configureInstance(IntegrationInstance $instance, array $config): void;

    /** Ścieżki do backupu danych instancji */
    public function backupPaths(IntegrationInstance $instance): array;
}
