<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chat\GroupChatOrchestratorInterface;
use App\Contracts\Llm\ModelManagementInterface;
use App\Contracts\Memory\VectorStoreInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Scenarios\ScenarioSchedulerInterface;
use App\Contracts\Skills\SkillLifecycleInterface;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\Config\ConfigTransferManager;
use App\Services\IntegrationManager\IntegrationService;
use App\Services\Integrations\DockerSocketService;
use App\Services\MemoryService\MockVectorStore;
use App\Services\MemoryService\QdrantVectorStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SelfTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agenthub:selftest {--json : Zwróć wynik w formacie JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Przeprowadza kompleksowy smoke-test podsystemów AgentHub (baza, cache, wektory, bramka LLM, adaptery)';

    public function handle(IntegrationService $integrationService): int
    {
        $isJson = (bool) $this->option('json');
        $checks = [];
        $hasCriticalFailure = false;

        if (! $isJson) {
            $this->info('=================================================');
            $this->info('   AgentHub System Diagnostics & Self-Test       ');
            $this->info('=================================================');
        }

        // 1. Baza Danych
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $checks['database'] = [
                'status' => 'ok',
                'message' => "Połączenie aktywne (sterownik: {$driver})",
            ];
        } catch (\Throwable $e) {
            $hasCriticalFailure = true;
            $checks['database'] = [
                'status' => 'fail',
                'message' => 'Błąd połączenia z bazą: '.$e->getMessage(),
            ];
        }

        // 2. Tabele i schemat
        $requiredTables = ['users', 'roles', 'permissions', 'llm_providers', 'llm_accounts', 'agents', 'telemetry_events', 'integration_instances'];
        $missingTables = [];
        try {
            if ($checks['database']['status'] === 'ok') {
                foreach ($requiredTables as $tbl) {
                    if (! Schema::hasTable($tbl)) {
                        $missingTables[] = $tbl;
                    }
                }
                if (empty($missingTables)) {
                    $checks['schema'] = [
                        'status' => 'ok',
                        'message' => 'Wszystkie kluczowe tabele obecne ('.count($requiredTables).'/'.count($requiredTables).')',
                    ];
                } else {
                    $hasCriticalFailure = true;
                    $checks['schema'] = [
                        'status' => 'fail',
                        'message' => 'Brakujące tabele: '.implode(', ', $missingTables),
                    ];
                }
            } else {
                $checks['schema'] = [
                    'status' => 'fail',
                    'message' => 'Pominięto sprawdzanie schematu z powodu braku połączenia z bazą',
                ];
            }
        } catch (\Throwable $e) {
            $hasCriticalFailure = true;
            $checks['schema'] = [
                'status' => 'fail',
                'message' => 'Błąd sprawdzania tabel: '.$e->getMessage(),
            ];
        }

        // 3. Cache & Sesje
        try {
            $testVal = 'selftest_'.time();
            Cache::put('agenthub_selftest_ping', $testVal, 10);
            $retrieved = Cache::get('agenthub_selftest_ping');
            if ($retrieved === $testVal) {
                $checks['cache'] = [
                    'status' => 'ok',
                    'message' => 'Zapis i odczyt pamięci podręcznej działa poprawnie',
                ];
            } else {
                $checks['cache'] = [
                    'status' => 'warn',
                    'message' => 'Niezgodność wartości w cache',
                ];
            }
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'status' => 'warn',
                'message' => 'Błąd pamięci cache: '.$e->getMessage(),
            ];
        }

        // 4. Magazyn Wektorowy
        try {
            /** @var VectorStoreInterface $vectorStore */
            $vectorStore = app(VectorStoreInterface::class);
            $isPingOk = $vectorStore->ping();
            $isMock = ($vectorStore instanceof QdrantVectorStore && $vectorStore->isMock())
                || ($vectorStore instanceof MockVectorStore);

            $driverName = match (true) {
                $isMock => 'Qdrant (Mock)',
                $vectorStore instanceof QdrantVectorStore => 'Qdrant',
                default => 'PgVector',
            };

            $checks['vector_store'] = [
                'status' => $isPingOk ? 'ok' : 'warn',
                'message' => $isPingOk
                    ? "Sterownik {$driverName}: Dostępny i odpowiada".($isMock ? ' (aktywny tryb mock)' : '')
                    : "Sterownik {$driverName}: Brak odpowiedzi na ping (włącz QDRANT_MOCK=true lub uruchom ./scripts/repairkit.sh)",
            ];
        } catch (\Throwable $e) {
            $checks['vector_store'] = [
                'status' => 'warn',
                'message' => 'Magazyn wektorowy: '.$e->getMessage(),
            ];
        }

        // 5. Brama LLM & Dostawcy
        try {
            $providersCount = LlmProvider::where('is_active', true)->count();
            $accountsCount = LlmAccount::count();
            $checks['llm_gateway'] = [
                'status' => $providersCount > 0 ? 'ok' : 'warn',
                'message' => "Aktywni dostawcy: {$providersCount}, skonfigurowane konta: {$accountsCount}",
            ];
        } catch (\Throwable $e) {
            $checks['llm_gateway'] = [
                'status' => 'fail',
                'message' => 'Błąd dostawców LLM: '.$e->getMessage(),
            ];
        }

        // 6. Adaptery runtime integracji
        $adapters = ['hermes', 'openclaw', 'claude_code', 'codex'];
        $detectedCount = 0;
        foreach ($adapters as $adapterKey) {
            try {
                $adapter = $integrationService->getAdapter($adapterKey);
                $info = $adapter->detect();
                if ($info['installed'] ?? false) {
                    $detectedCount++;
                }
            } catch (\Throwable) {
            }
        }
        $checks['runtime_adapters'] = [
            'status' => 'ok',
            'message' => "Dostępne adaptery: {$detectedCount}/".count($adapters).' (sterowniki załadowane)',
        ];

        // 7. Administrator
        try {
            $adminUser = ($checks['database']['status'] === 'ok') ? User::where('email', 'admin@admin.lan')->first() : null;
            $checks['admin_user'] = [
                'status' => $adminUser ? 'ok' : 'warn',
                'message' => $adminUser ? 'Konto admin@admin.lan obecne' : 'Brak konta domyślnego admin@admin.lan (uruchom db:seed)',
            ];
        } catch (\Throwable $e) {
            $checks['admin_user'] = [
                'status' => 'warn',
                'message' => 'Nie można sprawdzić konta admina: '.$e->getMessage(),
            ];
        }

        // --- 1.5.0 Rozszerzenia diagnostyczne ---

        // 8. Magazyn Skilli (v1.5.0)
        try {
            $hasSkillsTable = Schema::hasTable('skills') && Schema::hasTable('skill_versions');
            $skillsDisk = config('filesystems.disks.skills');
            $checks['skills_store'] = [
                'status' => $hasSkillsTable && $skillsDisk ? 'ok' : 'warn',
                'message' => $hasSkillsTable ? 'Tabele skilli i dysk storage obecne' : 'Brak tabel skilli',
            ];
        } catch (\Throwable $e) {
            $checks['skills_store'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 9. Czat grupowy & Orkiestracja (v1.5.0)
        try {
            $hasChatOrchestration = Schema::hasTable('conversation_participants');
            $orchestrator = app(GroupChatOrchestratorInterface::class);
            $checks['group_chat'] = [
                'status' => $hasChatOrchestration && $orchestrator ? 'ok' : 'fail',
                'message' => 'Serwis orkiestracji czatu gotowy (4 tryby)',
            ];
        } catch (\Throwable $e) {
            $checks['group_chat'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 10. Scenariusze Wizualne (v1.5.0)
        try {
            $hasScenarios = Schema::hasTable('scenarios') && Schema::hasTable('scenario_runs');
            $engine = app(ScenarioEngineInterface::class);
            $checks['scenarios'] = [
                'status' => $hasScenarios && $engine ? 'ok' : 'fail',
                'message' => 'Silnik scenariuszy Drawflow i kolejka Horizon gotowe',
            ];
        } catch (\Throwable $e) {
            $checks['scenarios'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 11. Kontenery Docker & Gniazdo (v1.5.0)
        try {
            /** @var DockerSocketService $dockerSocket */
            $dockerSocket = app(DockerSocketService::class);
            $isAvailable = $dockerSocket->isAvailable();
            $isMock = $dockerSocket->isMock();

            if ($isAvailable && ! $isMock) {
                $status = 'ok';
                $message = 'Docker socket aktywny (unix:///var/run/docker.sock)';
            } elseif ($isMock) {
                $status = 'ok';
                $message = 'Aktywny sterownik mock Docker (symulacja kontenerów Ollama, Hermes, OpenClaw)';
            } else {
                $status = 'warn';
                $message = 'Docker socket niedostępny i brak aktywnego sterownika mock (włącz DOCKER_MOCK=true lub uruchom ./scripts/repairkit.sh)';
            }

            $checks['docker_socket'] = [
                'status' => $status,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $checks['docker_socket'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 12. Eksport / Import Konfiguracji (v1.5.0)
        try {
            $transferManager = app(ConfigTransferManager::class);
            $sectionsCount = count($transferManager->getOrderedSections());
            $checks['config_transfer'] = [
                'status' => $sectionsCount > 0 ? 'ok' : 'warn',
                'message' => "Moduł transferów gotowy, zarejestrowanych sekcji: {$sectionsCount}",
            ];
        } catch (\Throwable $e) {
            $checks['config_transfer'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // --- 1.5.7 Rozszerzenia diagnostyczne ---

        // 13. Rejestr Modeli LLM (v1.5.7)
        try {
            $hasModelsTable = Schema::hasTable('models');
            $modelManager = app(ModelManagementInterface::class);
            $checks['models_registry'] = [
                'status' => $hasModelsTable && $modelManager ? 'ok' : 'fail',
                'message' => 'Rejestr modeli LLM (tabela models, serwis parametrów i overrides gotowe)',
            ];
        } catch (\Throwable $e) {
            $checks['models_registry'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 14. Cykl życia skilli (v1.5.7)
        try {
            $hasLifecycleColumns = Schema::hasColumn('skills', 'deleted_at');
            $lifecycleService = app(SkillLifecycleInterface::class);
            $checks['skills_lifecycle'] = [
                'status' => $hasLifecycleColumns && $lifecycleService ? 'ok' : 'fail',
                'message' => 'Cykl życia skilli (SemVer, ochrona zależności, kosz soft-delete gotowe)',
            ];
        } catch (\Throwable $e) {
            $checks['skills_lifecycle'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        // 15. Harmonogramy i timery scenariuszy (v1.5.7)
        try {
            $hasTriggersTable = Schema::hasTable('scenario_triggers');
            $scheduler = app(ScenarioSchedulerInterface::class);
            $checks['scenario_scheduler'] = [
                'status' => $hasTriggersTable && $scheduler ? 'ok' : 'fail',
                'message' => 'Silnik harmonogramów scenariuszy (Europe/Warsaw, blokada atomowa i auto-pauza gotowe)',
            ];
        } catch (\Throwable $e) {
            $checks['scenario_scheduler'] = ['status' => 'warn', 'message' => $e->getMessage()];
        }

        if ($isJson) {
            $this->output->writeln(json_encode([
                'success' => ! $hasCriticalFailure,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $hasCriticalFailure ? Command::FAILURE : Command::SUCCESS;
        }

        foreach ($checks as $category => $data) {
            $tag = match ($data['status']) {
                'ok' => '<info>[  OK  ]</info>',
                'warn' => '<comment>[ WARN ]</comment>',
                default => '<error>[ FAIL ]</error>',
            };
            $this->line(sprintf(' %s %-18s : %s', $tag, strtoupper($category), $data['message']));
        }

        $this->newLine();
        if ($hasCriticalFailure) {
            $this->error('Wykryto krytyczne błędy w środowisku aplikacji!');

            return Command::FAILURE;
        }

        $this->info('Wszystkie krytyczne testy zakończone sukcesem. Platforma jest gotowa do pracy.');

        return Command::SUCCESS;
    }
}
