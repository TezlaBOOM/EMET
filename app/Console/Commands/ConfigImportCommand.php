<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Config\ConfigTransferManager;
use Exception;
use Illuminate\Console\Command;

class ConfigImportCommand extends Command
{
    protected $signature = 'config:import 
                            {file : Ścieżka do pliku archiwum ZIP}
                            {--mode=merge : Tryb scalania (merge, overwrite, new_only)}
                            {--password= : Hasło do odszyfrowania pliku sekretów}
                            {--dry-run : Wykonaj wyłącznie walidację i plan importu bez zapisu w bazie}';

    protected $description = 'Import konfiguracji systemu AgentHub z archiwum ZIP z trybem dry-run (v1.5.0)';

    public function handle(ConfigTransferManager $transferManager): int
    {
        $filePath = (string) $this->argument('file');
        $mode = (string) $this->option('mode');
        $password = $this->option('password');
        $isDryRun = (bool) $this->option('dry-run');

        if (! in_array($mode, ['merge', 'overwrite', 'new_only'], true)) {
            $this->error("Nieobsługiwany tryb scalania: [{$mode}]. Dozwolone: merge, overwrite, new_only.");

            return 1;
        }

        $this->info("Wczytywanie paczki konfiguracyjnej: {$filePath} (tryb: {$mode})");

        if ($isDryRun) {
            $this->warn('Tryb DRY-RUN aktywny (żadne zmiany nie zostaną zapisane w bazie danych).');
            $planResult = $transferManager->planImport($filePath, ['mode' => $mode], $password);

            if (! $planResult['valid']) {
                $this->error('Walidacja archiwum nie powiodła się:');
                foreach ($planResult['errors'] as $err) {
                    $this->line(" - <fg=red>{$err}</>");
                }

                return 1;
            }

            $this->info('✓ Walidacja archiwum zakończona pomyślnie!');
            $this->table(
                ['Sekcja', 'Nowe rekordy', 'Aktualizacje', 'Pominięte', 'Konflikty'],
                collect($planResult['diff_report'])->map(function ($plan, $key) {
                    return [
                        $key,
                        count($plan['create'] ?? []),
                        count($plan['update'] ?? []),
                        count($plan['skip'] ?? []),
                        count($plan['conflicts'] ?? []),
                    ];
                })
            );

            return 0;
        }

        try {
            $transfer = $transferManager->applyImport($filePath, ['mode' => $mode], $password);

            $this->info('✓ Import konfiguracji zakończony sukcesem!');
            $this->line("  ID transferu: <comment>{$transfer->id}</comment>");
            $this->line('  Dodano rekordów: <comment>'.($transfer->stats['total_imported'] ?? 0).'</comment>');
            $this->line('  Zaktualizowano: <comment>'.($transfer->stats['total_updated'] ?? 0).'</comment>');
            $this->line("  Kopia bezpieczeństwa: <comment>{$transfer->backup_path}</comment>");

            return 0;
        } catch (Exception $e) {
            $this->error('Błąd podczas importu konfiguracji: '.$e->getMessage());

            return 1;
        }
    }
}
