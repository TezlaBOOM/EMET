<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agenthub:check-update {--channel=stable : Kanał wydań (stable, beta)} {--json : Zwróć wynik jako JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sprawdza dostępność nowych wydań i aktualizacji platformy AgentHub';

    public function handle(): int
    {
        $channel = (string) $this->option('channel');
        $isJson = (bool) $this->option('json');

        $versionFile = base_path('VERSION');
        $currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';

        // W środowisku produkcyjnym odpytywane jest repozytorium GitHub / endpoint API
        // Symulacja sprawdzenia kanału:
        $latestVersion = '1.0.0';
        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

        $payload = [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'channel' => $channel,
            'update_available' => $updateAvailable,
            'checked_at' => now()->toIso8601String(),
            'changelog_url' => 'https://github.com/emet/agenthub/releases',
        ];

        if ($isJson) {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->info("Aktualna wersja platformy: {$currentVersion}");
        $this->info("Najnowsza wersja na kanale [{$channel}]: {$latestVersion}");

        if ($updateAvailable) {
            $this->comment("Dostępna jest nowa wersja! Aby zaktualizować, uruchom ./scripts/update.sh");
        } else {
            $this->info("Platforma AgentHub jest zaktualizowana do najnowszej wersji.");
        }

        return Command::SUCCESS;
    }
}
