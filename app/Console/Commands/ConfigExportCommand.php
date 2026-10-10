<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Config\ConfigTransferManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigExportCommand extends Command
{
    protected $signature = 'config:export 
                            {--path= : Ścieżka docelowa pliku ZIP}
                            {--include-secrets : Dołącz zaszyfrowane sekrety}
                            {--password= : Hasło szyfrowania sekretów}';

    protected $description = 'Eksport konfiguracji systemu AgentHub do archiwum ZIP (v1.5.0)';

    public function handle(ConfigTransferManager $transferManager): int
    {
        $this->info('Rozpoczynanie eksportu konfiguracji AgentHub...');

        $includeSecrets = (bool) $this->option('include-secrets');
        $password = $this->option('password');

        if ($includeSecrets && empty($password)) {
            $password = $this->secret('Podaj hasło do zaszyfrowania pliku sekretów (Argon2id + XChaCha20):');
            if (empty($password)) {
                $this->error('Eksport przerwany: brak hasła dla szyfrowania sekretów.');

                return 1;
            }
        }

        try {
            $result = $transferManager->export([
                'include_secrets' => $includeSecrets,
            ], $password);

            $zipPath = $result['zip_path'];
            $targetPath = $this->option('path');

            if ($targetPath) {
                File::ensureDirectoryExists(dirname($targetPath));
                File::copy($zipPath, $targetPath);
                $finalPath = $targetPath;
            } else {
                $finalPath = $zipPath;
            }

            $this->info('✓ Eksport zakończony sukcesem!');
            $this->line("  Plik: <comment>{$finalPath}</comment>");
            $this->line("  SHA-256: <comment>{$result['file_hash']}</comment>");
            $this->line('  Sekrety: <comment>'.($includeSecrets ? 'Zaszyfrowane' : 'Pominięte').'</comment>');

            return 0;
        } catch (Exception $e) {
            $this->error('Błąd podczas eksportu konfiguracji: '.$e->getMessage());

            return 1;
        }
    }
}
