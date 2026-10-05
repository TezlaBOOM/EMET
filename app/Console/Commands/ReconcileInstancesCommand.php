<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IntegrationManager\IntegrationService;
use Illuminate\Console\Command;

class ReconcileInstancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agenthub:reconcile-instances';

    /**
     * The console command description.
     */
    protected $description = 'Weryfikuje stan zdrowia instancji zewnętrznych (Reconciliation Loop)';

    /**
     * Execute the console command.
     */
    public function handle(IntegrationService $integrationService): int
    {
        $this->info('Uruchamianie pętli uzgadniania stanu instancji...');

        $changes = $integrationService->reconcile();

        if (empty($changes)) {
            $this->info('Wszystkie instancje są w stanie stabilnym. Brak zmian.');
        } else {
            $this->warn('Zaktualizowano statusy instancji:');
            foreach ($changes as $change) {
                $this->line(" - [{$change['name']}]: {$change['old_status']} -> {$change['new_status']}");
            }
        }

        return self::SUCCESS;
    }
}
