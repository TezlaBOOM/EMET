<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelemetryCollector\TelemetryCollectorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetryRollupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agenthub:telemetry-rollup {--period=hourly : Typ agregacji (hourly, daily)} {--hours=1 : Ile godzin wstecz przetworzyć}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agreguje surowe logi wywołań LLM i telemetrii do tabeli rollupów godzinowych/dziennych';

    public function handle(TelemetryCollectorService $service): int
    {
        $period = (string) $this->option('period');
        $hours = (int) $this->option('hours');

        $this->info("Rozpoczynanie rollupów telemetrii ({$period}) dla ostatnich {$hours} h...");

        $totalAggregated = 0;

        for ($i = $hours; $i >= 0; $i--) {
            $target = now()->subHours($i);
            $count = $service->aggregateRollups($period, $target);
            $totalAggregated += $count;
        }

        $this->info("Zakończono rollup telemetrii. Zaktualizowano/utworzono {$totalAggregated} wpisów agregacji.");

        return Command::SUCCESS;
    }
}
