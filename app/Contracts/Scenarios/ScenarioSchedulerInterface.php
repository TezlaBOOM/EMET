<?php

declare(strict_types=1);

namespace App\Contracts\Scenarios;

use App\Models\ScenarioRun;
use App\Models\ScenarioTrigger;
use Carbon\CarbonInterface;

/**
 * Kontrakt silnika harmonogramów scenariuszy (v1.5.7).
 */
interface ScenarioSchedulerInterface
{
    /**
     * Oblicza następny termin uruchomienia na podstawie typu i parametrów harmonogramu.
     *
     * @param  array<string, mixed>  $schedule
     */
    public function calculateNextRun(
        string $scheduleType,
        array $schedule,
        string $timezone,
        ?CarbonInterface $from = null
    ): ?CarbonInterface;

    /**
     * Wylicza listę kolejnych terminów uruchomień dla podglądu w formularzu UI.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<int, string>
     */
    public function previewUpcomingRuns(
        string $scheduleType,
        array $schedule,
        string $timezone,
        int $count = 5
    ): array;

    /**
     * Sprawdza i uruchamia wszystkie wyzwalacze, których termin minął (dispatch due triggers).
     *
     * @return int Liczba uruchomionych wyzwalaczy
     */
    public function dispatchDueTriggers(): int;

    /**
     * Ręczne wymuszenie natychmiastowego uruchomienia timera ("Uruchom teraz").
     */
    public function triggerNow(ScenarioTrigger $trigger): ScenarioRun;
}
