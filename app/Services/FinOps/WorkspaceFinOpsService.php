<?php

declare(strict_types=1);

namespace App\Services\FinOps;

use App\Contracts\FinOps\WorkspaceFinOpsInterface;
use App\Models\Workspace;

class WorkspaceFinOpsService implements WorkspaceFinOpsInterface
{
    public function recordUsage(Workspace $workspace, int $tokens, float $costUsd): array
    {
        $newTokens = max(0, $workspace->tokens_used_today + max(0, $tokens));
        $newDailyUsd = round($workspace->spent_today_usd + max(0.0, $costUsd), 6);
        $newMonthlyUsd = round($workspace->spent_month_usd + max(0.0, $costUsd), 6);

        $workspace->update([
            'tokens_used_today' => $newTokens,
            'spent_today_usd' => $newDailyUsd,
            'spent_month_usd' => $newMonthlyUsd,
        ]);

        $dailyPct = $workspace->daily_budget_usd > 0
            ? ($newDailyUsd / $workspace->daily_budget_usd) * 100.0
            : 0.0;
        $monthlyPct = $workspace->monthly_budget_usd > 0
            ? ($newMonthlyUsd / $workspace->monthly_budget_usd) * 100.0
            : 0.0;
        $tokenPct = $workspace->daily_token_limit > 0
            ? ($newTokens / $workspace->daily_token_limit) * 100.0
            : 0.0;

        $maxUtilizationPct = round(max($dailyPct, $monthlyPct, $tokenPct), 2);
        $budgetExceeded = $maxUtilizationPct >= 100.0;
        $alertTriggered = $maxUtilizationPct >= (float) $workspace->alert_threshold_pct;

        return [
            'workspace_id' => $workspace->id,
            'spent_today_usd' => $newDailyUsd,
            'spent_month_usd' => $newMonthlyUsd,
            'tokens_used_today' => $newTokens,
            'budget_exceeded' => $budgetExceeded,
            'alert_triggered' => $alertTriggered,
            'utilization_pct' => $maxUtilizationPct,
        ];
    }

    public function resolveEffectiveModel(Workspace $workspace, string $requestedModel): array
    {
        $dailyExceeded = $workspace->daily_budget_usd > 0 && $workspace->spent_today_usd >= $workspace->daily_budget_usd;
        $monthlyExceeded = $workspace->monthly_budget_usd > 0 && $workspace->spent_month_usd >= $workspace->monthly_budget_usd;
        $tokensExceeded = $workspace->daily_token_limit > 0 && $workspace->tokens_used_today >= $workspace->daily_token_limit;

        if (($dailyExceeded || $monthlyExceeded || $tokensExceeded) && $workspace->fallback_to_local_on_exceed) {
            $reason = $dailyExceeded
                ? 'Przekroczono dzienny budżet USD przestrzeni roboczej'
                : ($monthlyExceeded
                    ? 'Przekroczono miesięczny budżet USD przestrzeni roboczej'
                    : 'Przekroczono dzienny limit tokenów przestrzeni roboczej');

            return [
                'effective_model' => $workspace->fallback_model ?: 'ollama/llama3.2',
                'downgraded_to_local' => true,
                'reason' => $reason,
            ];
        }

        return [
            'effective_model' => $requestedModel,
            'downgraded_to_local' => false,
            'reason' => null,
        ];
    }
}
