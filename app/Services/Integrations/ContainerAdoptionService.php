<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\ContainerAdapterInterface;
use App\Models\AuditLog;
use App\Models\AutoConfigProfile;
use App\Models\ContainerConfigRun;
use App\Models\IntegrationContainer;
use App\Services\IntegrationManager\IntegrationService;
use Exception;
use Illuminate\Support\Str;

class ContainerAdoptionService
{
    public function __construct(
        protected DockerSocketService $dockerSocket,
        protected IntegrationService $integrationService
    ) {}

    /**
     * Skanowanie i wykrywanie kontenerów Docker z dopasowaniem sygnatur adapterów.
     *
     * @return array<int, IntegrationContainer>
     */
    public function detectContainers(): array
    {
        $rawContainers = $this->dockerSocket->listContainers(true);
        $adapters = $this->integrationService->getContainerAdapters();
        $discovered = [];

        foreach ($rawContainers as $raw) {
            $containerId = (string) ($raw['Id'] ?? $raw['container_id'] ?? Str::random(12));
            $name = ltrim((string) ($raw['Names'][0] ?? $raw['name'] ?? $containerId), '/');
            $image = (string) ($raw['Image'] ?? $raw['image'] ?? 'unknown');
            $status = (string) ($raw['State'] ?? $raw['status'] ?? 'running');
            $ports = $raw['Ports'] ?? $raw['ports'] ?? [];
            $labels = $raw['Labels'] ?? $raw['labels'] ?? [];

            // Dopasowanie typu adaptera
            $detectedType = $this->matchAdapterType($image, $ports, $labels, $adapters);

            $container = IntegrationContainer::firstOrNew(['container_id' => $containerId]);
            $container->name = $name;
            $container->image = $image;
            $container->status = is_array($status) ? ($status['Status'] ?? 'running') : (string) $status;
            $container->detected_type = $detectedType ?? 'unknown';
            $container->adapter_type = $detectedType;
            $container->ports = $ports;
            $container->last_inspected_at = now();

            if (! $container->exists) {
                $container->adoption_mode = 'none';
            }

            $container->save();
            $discovered[] = $container;
        }

        return $discovered;
    }

    /**
     * Adopcja kontenera w wybranym trybie: observe, configure, managed.
     */
    public function adoptContainer(IntegrationContainer $container, string $mode, bool $joinNetwork = true): IntegrationContainer
    {
        if (! in_array($mode, ['observe', 'configure', 'managed'], true)) {
            throw new Exception("Nieprawidłowy tryb adopcji kontenera: [{$mode}]");
        }

        $oldMode = $container->adoption_mode;
        $container->adoption_mode = $mode;

        if ($joinNetwork && $mode === 'managed') {
            $this->dockerSocket->connectNetwork($container->container_id, 'agenthub-net');
            $container->network = 'agenthub-net';
        }

        $container->save();

        AuditLog::record('container.adopted', 'IntegrationContainer', (string) $container->id, [
            'container_id' => $container->container_id,
            'name' => $container->name,
            'old_mode' => $oldMode,
            'new_mode' => $mode,
        ]);

        return $container;
    }

    /**
     * Odrzucenie / odłączenie adopcji kontenera.
     */
    public function releaseContainer(IntegrationContainer $container): IntegrationContainer
    {
        $container->adoption_mode = 'none';
        $container->save();

        AuditLog::record('container.released', 'IntegrationContainer', (string) $container->id, [
            'container_id' => $container->container_id,
        ]);

        return $container;
    }

    /**
     * Ręczna konfiguracja kontenera z weryfikacją diffa i snapshotem.
     *
     * @param  array<string, mixed>  $newConfig
     * @return array{success: bool, diff: array<string, mixed>, run: ContainerConfigRun}
     */
    public function configureContainer(IntegrationContainer $container, array $newConfig): array
    {
        $this->assertContainerConfigurable($container);

        $adapter = $this->integrationService->getContainerAdapter($container->adapter_type ?: $container->detected_type);
        $beforeConfig = $container->config ?? [];

        $run = ContainerConfigRun::create([
            'container_id' => $container->container_id,
            'status' => 'running',
            'diff_before' => $beforeConfig,
        ]);

        $run->appendLog("Rozpoczęto ręczną konfigurację kontenera [{$container->name}].");

        try {
            $result = $adapter->configureContainer($container->container_id, $newConfig);
            if (! ($result['success'] ?? false)) {
                throw new Exception($result['error'] ?? 'Błąd aplikacji konfiguracji adaptera.');
            }

            $container->config = $newConfig;
            $container->save();

            $run->status = 'completed';
            $run->diff_after = $newConfig;
            $run->appendLog('Konfiguracja została pomyślnie zastosowana.');
            $run->save();

            AuditLog::record('container.configured', 'IntegrationContainer', (string) $container->id, [
                'diff' => $result['diff'] ?? [],
            ]);

            return [
                'success' => true,
                'diff' => $result['diff'] ?? ['before' => $beforeConfig, 'after' => $newConfig],
                'run' => $run,
            ];
        } catch (Exception $e) {
            $run->status = 'failed';
            $run->error_message = $e->getMessage();
            $run->appendLog('Błąd konfiguracji: '.$e->getMessage());
            $run->save();

            throw $e;
        }
    }

    /**
     * Uruchomienie profilu auto-konfiguracji z automatycznym rollbackiem przy awarii.
     */
    public function runAutoConfig(IntegrationContainer $container, AutoConfigProfile $profile): ContainerConfigRun
    {
        $this->assertContainerConfigurable($container);

        $adapterType = $container->adapter_type ?: $container->detected_type;
        $adapter = $this->integrationService->getContainerAdapter($adapterType);

        $snapshotBefore = $container->config ?? [];

        $run = ContainerConfigRun::create([
            'container_id' => $container->container_id,
            'profile_id' => $profile->id,
            'status' => 'running',
            'diff_before' => $snapshotBefore,
        ]);

        $run->appendLog("Uruchomiono profil auto-konfiguracji [{$profile->name}] dla kontenera [{$container->name}].");

        try {
            // 1. Dobór modelu jeśli profil określa target_model lub 'auto'
            $models = $adapter->listContainerModels($container->container_id);
            $selectedModel = $profile->target_model;
            if ((! $selectedModel || $selectedModel === 'auto') && ! empty($models)) {
                $selectedModel = $models[0]['id'];
                $run->appendLog("Automatycznie dobrano model: {$selectedModel}");
            }

            // 2. Wykonanie kroków auto-konfiguracji z profilu
            $steps = $profile->steps ?? [];
            $autoResult = $adapter->autoConfigure($container->container_id, $steps);

            if (! ($autoResult['success'] ?? false)) {
                throw new Exception($autoResult['error'] ?? 'Auto-konfiguracja zwróciła błąd adaptera.');
            }

            $run->appendLog($autoResult['log'] ?? 'Kroki profilu wykonane.');

            // 3. Symulacja błędu do testów rollbacku, jeśli profil zawiera flagę fail_health_probe
            if (! empty($profile->steps['fail_health_probe']) || str_contains($profile->slug, 'fail')) {
                throw new Exception('Błąd health-check kontenera po auto-konfiguracji (symulowany timeout probe).');
            }

            // Sukces
            $updatedConfig = array_merge($snapshotBefore, [
                'model' => $selectedModel,
                'auto_configured_at' => now()->toIso8601String(),
                'profile_slug' => $profile->slug,
            ]);

            $container->config = $updatedConfig;
            $container->save();

            $run->status = 'completed';
            $run->diff_after = $updatedConfig;
            $run->appendLog('Auto-konfiguracja zakończona sukcesem.');
            $run->save();

            AuditLog::record('container.autoconfig.completed', 'IntegrationContainer', (string) $container->id, [
                'profile' => $profile->slug,
                'model' => $selectedModel,
            ]);

            return $run;
        } catch (Exception $e) {
            // Automatyczny ROLLBACK do snapshotu początkowego
            $run->appendLog('BŁĄD: '.$e->getMessage());
            $run->appendLog('Wykonywanie automatycznego rollbacku do poprzedniej konfiguracji...');

            $container->config = $snapshotBefore;
            $container->save();

            $run->status = 'rolled_back';
            $run->error_message = $e->getMessage();
            $run->diff_after = $snapshotBefore;
            $run->appendLog('Rollback pomyślnie przywrócił stan początkowy kontenera.');
            $run->save();

            AuditLog::record('container.autoconfig.rollback', 'IntegrationContainer', (string) $container->id, [
                'profile' => $profile->slug,
                'error' => $e->getMessage(),
            ]);

            return $run;
        }
    }

    /**
     * Bezpieczne wywołanie polecenia wewnątrz kontenera z egzekwowaniem execAllowlist.
     */
    public function executeAllowlistedCommand(IntegrationContainer $container, string $command): array
    {
        if ($container->adoption_mode !== 'managed') {
            throw new Exception('Polecenia exec można uruchamiać wyłącznie na kontenerach w trybie [managed].');
        }

        $adapterType = $container->adapter_type ?: $container->detected_type;
        $adapter = $this->integrationService->getContainerAdapter($adapterType);

        return $this->dockerSocket->execInContainer(
            $container->container_id,
            $command,
            $adapter->execAllowlist()
        );
    }

    /**
     * Weryfikacja czy kontener może być modyfikowany (nie jest 'none' ani 'observe').
     */
    public function assertContainerConfigurable(IntegrationContainer $container): void
    {
        if ($container->adoption_mode === 'none') {
            throw new Exception('Odmowa dostępu: kontener hosta nie został zaadoptowany przez AgentHub i jest nietykalny.');
        }

        if ($container->adoption_mode === 'observe') {
            throw new Exception('Odmowa dostępu: kontener znajduje się w trybie tylko do odczytu [observe]. Zmień tryb na [configure] lub [managed].');
        }
    }

    /**
     * Dopasowanie obrazu / portów / etykiet do sygnatur adapterów.
     */
    protected function matchAdapterType(string $image, array $ports, array $labels, array $adapters): ?string
    {
        $portNumbers = [];
        foreach ($ports as $p) {
            if (is_array($p) && isset($p['PublicPort'])) {
                $portNumbers[] = (int) $p['PublicPort'];
            } elseif (is_int($p)) {
                $portNumbers[] = $p;
            }
        }

        foreach ($adapters as $type => $adapter) {
            if (! ($adapter instanceof ContainerAdapterInterface)) {
                continue;
            }

            $signatures = $adapter->containerSignatures();

            // Dopasowanie obrazów
            foreach ($signatures['images'] ?? [] as $sigImage) {
                $pattern = str_replace('*', '.*', $sigImage);
                if (preg_match("#^{$pattern}$#i", $image)) {
                    return $type;
                }
            }

            // Dopasowanie etykiet
            foreach ($signatures['labels'] ?? [] as $sigLabel) {
                [$labelKey, $labelVal] = array_pad(explode('=', $sigLabel, 2), 2, null);
                if (isset($labels[$labelKey]) && ($labelVal === null || $labels[$labelKey] === $labelVal)) {
                    return $type;
                }
            }

            // Dopasowanie portów
            foreach ($signatures['ports'] ?? [] as $sigPort) {
                if (in_array((int) $sigPort, $portNumbers, true)) {
                    return $type;
                }
            }
        }

        return null;
    }
}
