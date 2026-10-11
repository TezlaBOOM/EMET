<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Scenarios\ScenarioSchedulerInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchDueScenariosCommand extends Command
{
    protected $signature = 'scenarios:dispatch-due';

    protected $description = 'Uruchamia zaplanowane scenariusze, których termin wyzwolenia minął (z atomową blokadą Cache::lock)';

    public function handle(ScenarioSchedulerInterface $scheduler): int
    {
        $lock = Cache::lock('scenarios:dispatch-due', 55);

        if (! $lock->get()) {
            $this->warn('Inny proces już przetwarza harmonogram scenariuszy (blokada cache aktywna).');

            return self::SUCCESS;
        }

        try {
            $dispatchedCount = $scheduler->dispatchDueTriggers();
            $this->info("Pomyślnie przetworzono i uruchomiono {$dispatchedCount} wyzwalaczy scenariuszy.");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
