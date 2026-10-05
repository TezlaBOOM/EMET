<?php

declare(strict_types=1);

namespace App\Services\TelemetryCollector;

use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Events\AgentTelemetryBroadcastEvent;
use App\Models\Agent;
use App\Models\IntegrationInstance;
use App\Models\LlmCall;
use App\Models\TelemetryEvent;
use App\Models\TelemetryRollup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TelemetryCollectorService implements TelemetryCollectorInterface
{
    /**
     * Rejestruje pojedyncze zdarzenie telemetryczne agenta lub runtime'u i emituje zdarzenie Reverb
     */
    public function recordEvent(string $type, ?int $agentId = null, ?string $runId = null, array $payload = []): TelemetryEvent
    {
        $event = TelemetryEvent::create([
            'agent_id' => $agentId,
            'run_id' => $runId,
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        try {
            event(new AgentTelemetryBroadcastEvent($event));
        } catch (\Throwable) {
            // Ignorujemy błąd broadcastu jeśli Reverb nie jest uruchomiony lokalnie
        }

        return $event;
    }

    /**
     * Zwraca podsumowanie aktywności agentów w czasie rzeczywistym
     */
    public function getActiveAgentsSummary(): array
    {
        $agents = Agent::all();
        $items = [];
        $activeCount = 0;

        foreach ($agents as $agent) {
            // Sprawdzenie ostatniego eventu
            $lastEvent = TelemetryEvent::where('agent_id', $agent->id)->latest('id')->first();
            $lastCall = LlmCall::where('agent_id', $agent->id)->latest('created_at')->first();

            $status = 'idle';
            $currentTask = 'Oczekuje na zadanie';

            if ($lastEvent) {
                if ($lastEvent->type === 'run.started' || $lastEvent->type === 'tool.called') {
                    $status = 'working';
                    $activeCount++;
                    $currentTask = $lastEvent->payload['task'] ?? $lastEvent->payload['tool'] ?? 'Wykonywanie zadania';
                } elseif ($lastEvent->type === 'error') {
                    $status = 'error';
                    $currentTask = 'Wystąpił błąd: ' . ($lastEvent->payload['message'] ?? 'Nieznany');
                }
            }

            $items[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'runtime' => $agent->runtime,
                'status' => $status,
                'current_task' => $currentTask,
                'total_calls' => LlmCall::where('agent_id', $agent->id)->count(),
                'total_tokens' => (int) LlmCall::where('agent_id', $agent->id)->sum('total_tokens'),
                'last_activity' => $lastEvent?->created_at ?? $lastCall?->created_at,
            ];
        }

        return [
            'total_agents' => $agents->count(),
            'active_count' => $activeCount,
            'agents' => $items,
        ];
    }

    /**
     * Zwraca zagregowane statystyki zużycia tokenów
     */
    public function getTokenUsageMetrics(string $period = '24h'): array
    {
        $since = $this->resolveSinceDate($period);

        $calls = LlmCall::where('created_at', '>=', $since)->get();

        $promptTokens = (int) $calls->sum('prompt_tokens');
        $completionTokens = (int) $calls->sum('completion_tokens');
        $totalTokens = (int) $calls->sum('total_tokens');

        // Podział na modele
        $byModel = $calls->groupBy('model')->map(function ($group, $model) {
            return [
                'model' => $model,
                'calls' => $group->count(),
                'prompt_tokens' => (int) $group->sum('prompt_tokens'),
                'completion_tokens' => (int) $group->sum('completion_tokens'),
                'total_tokens' => (int) $group->sum('total_tokens'),
                'cost_usd' => (float) $group->sum('estimated_cost_usd'),
            ];
        })->values()->toArray();

        // Podział na dostawców
        $byProvider = $calls->groupBy('provider_id')->map(function ($group, $providerId) {
            $provider = \App\Models\LlmProvider::find($providerId);
            return [
                'provider_id' => $providerId,
                'provider_name' => $provider?->name ?? "Provider #{$providerId}",
                'calls' => $group->count(),
                'total_tokens' => (int) $group->sum('total_tokens'),
                'cost_usd' => (float) $group->sum('estimated_cost_usd'),
            ];
        })->values()->toArray();

        return [
            'period' => $period,
            'total_calls' => $calls->count(),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'by_model' => $byModel,
            'by_provider' => $byProvider,
        ];
    }

    /**
     * Zwraca metryki wydajnościowe (TTFT, p50, p95 latencji, sukces vs błędy)
     */
    public function getPerformanceMetrics(string $period = '24h'): array
    {
        $since = $this->resolveSinceDate($period);

        $calls = LlmCall::where('created_at', '>=', $since)->get();
        $totalCalls = $calls->count();

        if ($totalCalls === 0) {
            return [
                'total_calls' => 0,
                'avg_ttft_ms' => 0,
                'avg_duration_ms' => 0,
                'p50_duration_ms' => 0,
                'p95_duration_ms' => 0,
                'success_rate_percent' => 100.0,
                'error_count' => 0,
            ];
        }

        $durations = $calls->pluck('duration_ms')->sort()->values()->toArray();
        $ttfts = $calls->whereNotNull('ttft_ms')->pluck('ttft_ms');

        $avgTtft = $ttfts->count() > 0 ? (int) round($ttfts->avg()) : 0;
        $avgDuration = (int) round($calls->avg('duration_ms'));

        $p50Index = (int) floor(0.50 * (count($durations) - 1));
        $p95Index = (int) floor(0.95 * (count($durations) - 1));

        $p50 = $durations[$p50Index] ?? 0;
        $p95 = $durations[$p95Index] ?? 0;

        $errors = $calls->where('status', '!=', 'success')->count();
        $successRate = round((($totalCalls - $errors) / $totalCalls) * 100, 1);

        return [
            'total_calls' => $totalCalls,
            'avg_ttft_ms' => $avgTtft,
            'avg_duration_ms' => $avgDuration,
            'p50_duration_ms' => $p50,
            'p95_duration_ms' => $p95,
            'success_rate_percent' => $successRate,
            'error_count' => $errors,
        ];
    }

    /**
     * Zwraca podsumowanie szacunkowych kosztów
     */
    public function getCostSummary(string $period = '24h'): array
    {
        $since = $this->resolveSinceDate($period);

        $calls = LlmCall::where('created_at', '>=', $since)->get();
        $totalCost = (float) $calls->sum('estimated_cost_usd');

        $byModel = $calls->groupBy('model')->map(function ($group, $model) {
            return [
                'model' => $model,
                'calls' => $group->count(),
                'tokens' => (int) $group->sum('total_tokens'),
                'cost_usd' => (float) round($group->sum('estimated_cost_usd'), 6),
            ];
        })->sortByDesc('cost_usd')->values()->toArray();

        return [
            'total_cost_usd' => round($totalCost, 4),
            'by_model' => $byModel,
        ];
    }

    /**
     * Zwraca zagregowany stan zdrowia integracji i instancji
     */
    public function getIntegrationsHealthSummary(): array
    {
        $instances = IntegrationInstance::all();

        $running = $instances->where('status', 'running')->count();
        $degraded = $instances->where('status', 'degraded')->count();
        $error = $instances->where('status', 'error')->count();
        $stopped = $instances->where('status', 'stopped')->count();

        return [
            'total_instances' => $instances->count(),
            'running' => $running,
            'degraded' => $degraded,
            'error' => $error,
            'stopped' => $stopped,
            'instances' => $instances->map(function ($inst) {
                return [
                    'id' => $inst->id,
                    'name' => $inst->name,
                    'type' => $inst->type,
                    'mode' => $inst->mode,
                    'status' => $inst->status,
                    'port' => $inst->port,
                    'health_checked_at' => $inst->health_checked_at,
                    'last_error' => $inst->last_error,
                ];
            })->toArray(),
        ];
    }

    /**
     * Wykonuje rollup danych telemetrycznych dla określonego okresu (T048)
     */
    public function aggregateRollups(string $periodType = 'hourly', ?Carbon $targetPeriod = null): int
    {
        $targetPeriod = $targetPeriod ?? now()->subHour()->startOfHour();

        if ($periodType === 'hourly') {
            $start = $targetPeriod->copy()->startOfHour();
            $end = $targetPeriod->copy()->endOfHour();
        } else {
            $start = $targetPeriod->copy()->startOfDay();
            $end = $targetPeriod->copy()->endOfDay();
        }

        $calls = LlmCall::whereBetween('created_at', [$start, $end])->get();

        if ($calls->isEmpty()) {
            return 0;
        }

        $grouped = $calls->groupBy(function ($call) {
            return implode(':', [
                $call->agent_id ?? 0,
                $call->provider_id ?? 0,
                $call->model ?? 'unknown',
            ]);
        });

        $recordsCreated = 0;

        foreach ($grouped as $key => $group) {
            [$agentId, $providerId, $model] = explode(':', $key);

            $durations = $group->pluck('duration_ms')->sort()->values()->toArray();
            $p95Index = (int) floor(0.95 * (count($durations) - 1));
            $p95 = $durations[$p95Index] ?? 0;

            $ttfts = $group->whereNotNull('ttft_ms')->pluck('ttft_ms');
            $avgTtft = $ttfts->count() > 0 ? (int) round($ttfts->avg()) : 0;

            TelemetryRollup::updateOrCreate(
                [
                    'period_type' => $periodType,
                    'period_start' => $start,
                    'agent_id' => $agentId > 0 ? (int) $agentId : null,
                    'provider_id' => $providerId > 0 ? (int) $providerId : null,
                    'model' => $model,
                ],
                [
                    'total_calls' => $group->count(),
                    'total_prompt_tokens' => (int) $group->sum('prompt_tokens'),
                    'total_completion_tokens' => (int) $group->sum('completion_tokens'),
                    'total_tokens' => (int) $group->sum('total_tokens'),
                    'avg_ttft_ms' => $avgTtft,
                    'avg_duration_ms' => (int) round($group->avg('duration_ms')),
                    'p95_duration_ms' => $p95,
                    'total_cost_usd' => (float) $group->sum('estimated_cost_usd'),
                    'error_count' => $group->where('status', '!=', 'success')->count(),
                ]
            );

            $recordsCreated++;
        }

        return $recordsCreated;
    }

    protected function resolveSinceDate(string $period): Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }
}
