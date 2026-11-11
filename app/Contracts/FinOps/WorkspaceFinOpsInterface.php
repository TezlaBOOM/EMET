<?php

declare(strict_types=1);

namespace App\Contracts\FinOps;

use App\Models\Workspace;

interface WorkspaceFinOpsInterface
{
    /**
     * Rejestruje zużycie tokenów oraz koszt USD dla wskazanej przestrzeni roboczej.
     *
     * @return array{
     *     workspace_id: int,
     *     spent_today_usd: float,
     *     spent_month_usd: float,
     *     tokens_used_today: int,
     *     budget_exceeded: bool,
     *     alert_triggered: bool,
     *     utilization_pct: float
     * }
     */
    public function recordUsage(Workspace $workspace, int $tokens, float $costUsd): array;

    /**
     * Wyznacza docelowy model dla przestrzeni roboczej (z automatycznym przełączeniem na model lokalny po przekroczeniu budżetu).
     *
     * @return array{
     *     effective_model: string,
     *     downgraded_to_local: bool,
     *     reason: string|null
     * }
     */
    public function resolveEffectiveModel(Workspace $workspace, string $requestedModel): array;
}
