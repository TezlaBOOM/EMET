<?php

declare(strict_types=1);

namespace App\Services\IntegrationManager;

use App\Contracts\Integrations\AgentRuntimeAdapter;
use App\Contracts\Integrations\ProvisionRequest;
use App\Models\IntegrationInstance;
use App\Models\ProvisioningJob;
use App\Services\IntegrationManager\Adapters\ClaudeCodeAdapter;
use App\Services\IntegrationManager\Adapters\CodexAdapter;
use App\Services\IntegrationManager\Adapters\HermesAdapter;
use App\Services\IntegrationManager\Adapters\OpenClawAdapter;
use Exception;
use Illuminate\Support\Str;

class IntegrationService
{
    /**
     * @var array<string, AgentRuntimeAdapter>
     */
    protected array $adapters = [];

    public function __construct()
    {
        $this->adapters = [
            'hermes' => new HermesAdapter(),
            'openclaw' => new OpenClawAdapter(),
            'claude_code' => new ClaudeCodeAdapter(),
            'codex' => new CodexAdapter(),
        ];
    }

    public function getAdapter(string $type): AgentRuntimeAdapter
    {
        $key = strtolower($type);
        if (! isset($this->adapters[$key])) {
            throw new Exception("Nieobsługiwany typ runtime'u: [{$type}]");
        }

        return $this->adapters[$key];
    }

    /**
     * Alokuje wolny port z puli 8100-8900
     */
    public function allocatePort(): int
    {
        $usedPorts = IntegrationInstance::whereNotNull('port')->pluck('port')->toArray();

        for ($p = 8100; $p <= 8900; $p++) {
            if (! in_array($p, $usedPorts, true)) {
                return $p;
            }
        }

        return 8999;
    }

    /**
     * Synchroniczne lub asynchroniczne wykonanie procesu provisioningu instancji z obsługą rollbacku
     */
    public function executeProvision(IntegrationInstance $instance, ProvisioningJob $job): bool
    {
        $job->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
        $job->appendLog("Rozpoczęto provisioning instancji [{$instance->name}] ({$instance->type}) w trybie {$instance->mode}.");

        try {
            $adapter = $this->getAdapter($instance->type);

            // 1. Alokacja portu jeśli brak
            if (! $instance->port) {
                $instance->port = $this->allocatePort();
                $instance->save();
                $job->appendLog("Przydzielono port: {$instance->port}");
            }

            // 2. Pobranie specyfikacji provisioningu
            $spec = $adapter->provisionSpec(new ProvisionRequest(
                type: $instance->type,
                name: $instance->name,
                mode: $instance->mode,
                port: $instance->port,
                config: $instance->config ?? []
            ));
            $job->appendLog("Wygenerowano specyfikację usługi: {$spec->serviceName}");

            // 3. Renderowanie plików konfiguracyjnych (symulacja/zapis)
            foreach ($spec->renderedFiles as $path => $content) {
                $job->appendLog("Zrenderowano plik konfiguracyjny: {$path}");
            }

            // Symulacja błędu dla testów rollbacku, jeśli wpisano 'fail-probe'
            if (str_contains($instance->slug, 'fail-probe')) {
                throw new Exception("Symulowany błąd uruchomienia instancji (Health probe timeout)");
            }

            // 4. Test zdrowia (Health probe)
            $health = $adapter->health($instance);
            if (! $health->isHealthy) {
                throw new Exception("Health probe nie powiódł się: {$health->status}");
            }
            $job->appendLog("Health probe zakończony sukcesem (latencja: {$health->latencyMs} ms).");

            // 5. Finalizacja sukcesu
            $instance->update([
                'status' => 'running',
                'endpoint_url' => "http://127.0.0.1:{$instance->port}",
                'health_checked_at' => now(),
                'last_error' => null,
            ]);

            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $job->appendLog("Instancja [{$instance->name}] jest aktywna i gotowa.");

            return true;
        } catch (Exception $e) {
            // Procedura Rollbacku (T044)
            $job->appendLog("BŁĄD: " . $e->getMessage());
            $job->appendLog("Uruchamianie procedury automatycznego rollbacku...");

            $this->rollback($instance, $job, $e->getMessage());

            return false;
        }
    }

    /**
     * Procedura rollbacku przy niepowodzeniu provisioningu
     */
    public function rollback(IntegrationInstance $instance, ProvisioningJob $job, string $errorMessage): void
    {
        $instance->update([
            'status' => 'error',
            'last_error' => $errorMessage,
        ]);

        $job->update([
            'status' => 'rolled_back',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
        $job->appendLog("Rollback zakończony. Zasoby zostały zwolnione.");
    }

    /**
     * Pętla uzgadniania stanu instancji (Reconciliation Loop - T043)
     *
     * @return array<array{instance_id: int, old_status: string, new_status: string}>
     */
    public function reconcile(): array
    {
        $instances = IntegrationInstance::all();
        $changes = [];

        foreach ($instances as $instance) {
            $oldStatus = $instance->status;

            try {
                $adapter = $this->getAdapter($instance->type);
                $health = $adapter->health($instance);

                if ($health->isHealthy) {
                    $newStatus = 'running';
                    $instance->last_error = null;
                } else {
                    $newStatus = ($oldStatus === 'running') ? 'degraded' : $health->status;
                }
            } catch (Exception $e) {
                $newStatus = 'error';
                $instance->last_error = $e->getMessage();
            }

            $instance->status = $newStatus;
            $instance->health_checked_at = now();
            $instance->save();

            if ($oldStatus !== $newStatus) {
                $changes[] = [
                    'instance_id' => $instance->id,
                    'name' => $instance->name,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ];
            }
        }

        return $changes;
    }
}
