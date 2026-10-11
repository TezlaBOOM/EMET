<?php

declare(strict_types=1);

namespace App\Services\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Scenarios\ScenarioSchedulerInterface;
use App\Models\ScenarioRun;
use App\Models\ScenarioTrigger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Str;
use Throwable;

class ScenarioSchedulerService implements ScenarioSchedulerInterface
{
    public function __construct(
        protected ScenarioEngineInterface $engine
    ) {}

    /**
     * Oblicza następny termin uruchomienia na podstawie typu i parametrów harmonogramu.
     *
     * @param  array<string, mixed>  $schedule
     */
    public function calculateNextRun(
        string $scheduleType,
        array $schedule,
        string $timezone = 'Europe/Warsaw',
        ?CarbonInterface $from = null
    ): ?CarbonInterface {
        $from = $from ? Carbon::instance($from)->setTimezone($timezone) : Carbon::now($timezone);

        return match ($scheduleType) {
            'once' => $this->calculateOnce($schedule, $timezone, $from),
            'interval' => $this->calculateInterval($schedule, $from),
            'daily' => $this->calculateDaily($schedule, $timezone, $from),
            'weekly' => $this->calculateWeekly($schedule, $timezone, $from),
            'monthly' => $this->calculateMonthly($schedule, $timezone, $from),
            'cron' => $this->calculateCron($schedule, $timezone, $from),
            default => null,
        };
    }

    /**
     * Wylicza listę kolejnych terminów uruchomień dla podglądu w formularzu UI.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<int, string>
     */
    public function previewUpcomingRuns(
        string $scheduleType,
        array $schedule,
        string $timezone = 'Europe/Warsaw',
        int $count = 5
    ): array {
        $runs = [];
        $cursor = Carbon::now($timezone);

        for ($i = 0; $i < $count; $i++) {
            $next = $this->calculateNextRun($scheduleType, $schedule, $timezone, $cursor);
            if (! $next) {
                break;
            }
            $runs[] = $next->format('Y-m-d H:i:s').' ('.$timezone.')';
            $cursor = $next;
        }

        return $runs;
    }

    /**
     * Sprawdza i uruchamia wszystkie wyzwalacze, których termin minął (dispatch due triggers).
     *
     * @return int Liczba uruchomionych wyzwalaczy
     */
    public function dispatchDueTriggers(): int
    {
        $dueTriggers = ScenarioTrigger::where('enabled', true)
            ->where('state', ScenarioTrigger::STATE_ACTIVE)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        $dispatched = 0;

        foreach ($dueTriggers as $trigger) {
            try {
                $this->triggerNow($trigger);
                $trigger->consecutive_failures = 0;
                $trigger->last_error = null;
                $dispatched++;
            } catch (Throwable $e) {
                $trigger->consecutive_failures++;
                $trigger->last_error = $e->getMessage();

                // Auto-pauza po 5 kolejnych awariach (zgodnie ze specyfikacją FR-023)
                if ($trigger->consecutive_failures >= 5) {
                    $trigger->state = ScenarioTrigger::STATE_ERROR;
                }
            }

            // Obliczenie kolejnego terminu uruchomienia
            $trigger->runs_count++;
            if ($trigger->max_runs && $trigger->runs_count >= $trigger->max_runs) {
                $trigger->state = ScenarioTrigger::STATE_FINISHED;
                $trigger->next_run_at = null;
            } else {
                $trigger->next_run_at = $this->calculateNextRun(
                    $trigger->schedule_type,
                    $trigger->schedule ?? [],
                    $trigger->timezone ?? 'Europe/Warsaw',
                    now($trigger->timezone ?? 'Europe/Warsaw')
                );
            }

            $trigger->save();
        }

        return $dispatched;
    }

    /**
     * Ręczne wymuszenie natychmiastowego uruchomienia timera ("Uruchom teraz").
     */
    public function triggerNow(ScenarioTrigger $trigger): ScenarioRun
    {
        $scenario = $trigger->scenario;
        if (! $scenario) {
            throw new \RuntimeException("Brak scenariusza dla wyzwalacza ID {$trigger->id}");
        }

        $idempotencyKey = "trigger-{$trigger->id}-".now()->format('YmdHi').'-'.Str::random(6);

        $run = ScenarioRun::create([
            'scenario_id' => $scenario->id,
            'version_id' => $scenario->current_version_id,
            'trigger_id' => $trigger->id,
            'trigger_type' => 'scheduled',
            'status' => ScenarioRun::STATUS_PENDING,
            'scheduled_for' => $trigger->next_run_at ?? now(),
            'idempotency_key' => $idempotencyKey,
            'input' => $trigger->input ?? [],
            'created_by' => $scenario->created_by,
        ]);

        $this->engine->startRun(
            scenarioId: $scenario->id,
            versionId: $scenario->current_version_id,
            input: $trigger->input ?? [],
            triggerType: 'scheduled'
        );

        $trigger->update([
            'last_run_at' => now(),
            'last_run_id' => (string) $run->id,
        ]);

        return $run;
    }

    private function calculateOnce(array $schedule, string $timezone, Carbon $from): ?Carbon
    {
        $dtStr = $schedule['datetime'] ?? $schedule['run_at'] ?? $schedule['time'] ?? null;
        if (! $dtStr) {
            return null;
        }

        $target = Carbon::parse($dtStr, $timezone);

        return $target->greaterThan($from) ? $target : null;
    }

    private function calculateInterval(array $schedule, Carbon $from): Carbon
    {
        $minutes = max(1, (int) ($schedule['interval_minutes'] ?? $schedule['minutes'] ?? 15));

        return $from->copy()->addMinutes($minutes);
    }

    private function calculateDaily(array $schedule, string $timezone, Carbon $from): Carbon
    {
        $time = $schedule['time'] ?? '00:00';
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);

        $target = $from->copy()->setTime($hour, $minute, 0);
        if ($target->lessThanOrEqualTo($from)) {
            $target->addDay();
        }

        return $target;
    }

    private function calculateWeekly(array $schedule, string $timezone, Carbon $from): Carbon
    {
        $time = $schedule['time'] ?? '00:00';
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);

        $rawDays = $schedule['days_of_week'] ?? $schedule['days'] ?? [1];
        $dayMap = [
            'monday' => 1, 'mon' => 1,
            'tuesday' => 2, 'tue' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday' => 4, 'thu' => 4,
            'friday' => 5, 'fri' => 5,
            'saturday' => 6, 'sat' => 6,
            'sunday' => 7, 'sun' => 7,
        ];
        $daysOfWeek = array_map(function ($day) use ($dayMap) {
            if (is_numeric($day)) {
                return (int) $day;
            }

            return $dayMap[strtolower((string) $day)] ?? 1;
        }, (array) $rawDays);

        $cursor = $from->copy();
        for ($i = 0; $i < 8; $i++) {
            $dayOfWeek = $cursor->dayOfWeekIso;
            if (in_array($dayOfWeek, $daysOfWeek, true)) {
                $target = $cursor->copy()->setTime($hour, $minute, 0);
                if ($target->greaterThan($from)) {
                    return $target;
                }
            }
            $cursor->addDay();
        }

        return $from->copy()->addWeek()->setTime($hour, $minute, 0);
    }

    private function calculateMonthly(array $schedule, string $timezone, Carbon $from): Carbon
    {
        $time = $schedule['time'] ?? '00:00';
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);
        $dayOfMonth = (int) ($schedule['day_of_month'] ?? 1);

        $target = $from->copy()->setDay(min($dayOfMonth, $from->daysInMonth))->setTime($hour, $minute, 0);
        if ($target->lessThanOrEqualTo($from)) {
            $target = $from->copy()->addMonth();
            $target->setDay(min($dayOfMonth, $target->daysInMonth))->setTime($hour, $minute, 0);
        }

        return $target;
    }

    private function calculateCron(array $schedule, string $timezone, Carbon $from): ?Carbon
    {
        $expression = $schedule['expression'] ?? $schedule['cron'] ?? $schedule['cron_expression'] ?? '* * * * *';
        try {
            $cron = new CronExpression($expression);
            $nextDateTime = $cron->getNextRunDate($from->toDateTime(), 0, false, $timezone);

            return Carbon::instance($nextDateTime)->setTimezone($timezone);
        } catch (Throwable) {
            return null;
        }
    }
}
