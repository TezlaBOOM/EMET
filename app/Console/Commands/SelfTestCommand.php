<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Memory\VectorStoreInterface;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\IntegrationManager\IntegrationService;
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
            $this->info("=================================================");
            $this->info("   AgentHub System Diagnostics & Self-Test       ");
            $this->info("=================================================");
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
                'message' => "Błąd połączenia z bazą: " . $e->getMessage(),
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
                        'message' => "Wszystkie kluczowe tabele obecne (" . count($requiredTables) . "/" . count($requiredTables) . ")",
                    ];
                } else {
                    $hasCriticalFailure = true;
                    $checks['schema'] = [
                        'status' => 'fail',
                        'message' => "Brakujące tabele: " . implode(', ', $missingTables),
                    ];
                }
            } else {
                $checks['schema'] = [
                    'status' => 'fail',
                    'message' => "Pominięto sprawdzanie schematu z powodu braku połączenia z bazą",
                ];
            }
        } catch (\Throwable $e) {
            $hasCriticalFailure = true;
            $checks['schema'] = [
                'status' => 'fail',
                'message' => "Błąd sprawdzania tabel: " . $e->getMessage(),
            ];
        }

        // 3. Cache & Sesje
        try {
            $testVal = 'selftest_' . time();
            Cache::put('agenthub_selftest_ping', $testVal, 10);
            $retrieved = Cache::get('agenthub_selftest_ping');
            if ($retrieved === $testVal) {
                $checks['cache'] = [
                    'status' => 'ok',
                    'message' => "Zapis i odczyt pamięci podręcznej działa poprawnie",
                ];
            } else {
                $checks['cache'] = [
                    'status' => 'warn',
                    'message' => "Niezgodność wartości w cache",
                ];
            }
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'status' => 'warn',
                'message' => "Błąd pamięci cache: " . $e->getMessage(),
            ];
        }

        // 4. Magazyn Wektorowy
        try {
            $vectorStore = app(VectorStoreInterface::class);
            $isPingOk = $vectorStore->ping();
            $driverName = $vectorStore instanceof \App\Services\MemoryService\QdrantVectorStore ? 'Qdrant' : 'PgVector';
            $checks['vector_store'] = [
                'status' => $isPingOk ? 'ok' : 'warn',
                'message' => "Sterownik {$driverName}: " . ($isPingOk ? 'Dostępny i odpowiada' : 'Brak odpowiedzi na ping'),
            ];
        } catch (\Throwable $e) {
            $checks['vector_store'] = [
                'status' => 'warn',
                'message' => "Magazyn wektorowy: " . $e->getMessage(),
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
                'message' => "Błąd dostawców LLM: " . $e->getMessage(),
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
            } catch (\Throwable) {}
        }
        $checks['runtime_adapters'] = [
            'status' => 'ok',
            'message' => "Dostępne adaptery: {$detectedCount}/" . count($adapters) . " (sterowniki załadowane)",
        ];

        // 7. Administrator
        try {
            $adminUser = ($checks['database']['status'] === 'ok') ? User::where('email', 'admin@admin.lan')->first() : null;
            $checks['admin_user'] = [
                'status' => $adminUser ? 'ok' : 'warn',
                'message' => $adminUser ? "Konto admin@admin.lan obecne" : "Brak konta domyślnego admin@admin.lan (uruchom db:seed)",
            ];
        } catch (\Throwable $e) {
            $checks['admin_user'] = [
                'status' => 'warn',
                'message' => "Nie można sprawdzić konta admina: " . $e->getMessage(),
            ];
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
            $this->line(sprintf(" %s %-18s : %s", $tag, strtoupper($category), $data['message']));
        }

        $this->newLine();
        if ($hasCriticalFailure) {
            $this->error("Wykryto krytyczne błędy w środowisku aplikacji!");
            return Command::FAILURE;
        }

        $this->info("Wszystkie krytyczne testy zakończone sukcesem. Platforma jest gotowa do pracy.");
        return Command::SUCCESS;
    }
}
