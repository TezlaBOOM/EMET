<?php

declare(strict_types=1);

namespace App\Contracts\Agents;

use App\Models\AgentCooperation;

interface AgentCooperationInterface
{
    /**
     * Uruchamia wieloagentową sesję dyskusji i wykonania zadania w ramach spiętego zespołu agentów
     * wraz z monitorowaniem temperatury układu i bezpiecznikiem przegrzania (Overheat Guard).
     */
    public function runCooperationTask(
        AgentCooperation $cooperation,
        string $taskPrompt,
        ?int $roundsOverride = null
    ): AgentCooperation;

    /**
     * Oblicza bieżące obciążenie termiczne układu kooperacji oraz weryfikuje progi bezpiecznika przegrzania.
     *
     * @return array{
     *     temperature: float,
     *     thermal_status: string,
     *     is_overheated: bool,
     *     reason: ?string,
     *     message: ?string
     * }
     */
    public function evaluateOverheatGuard(
        AgentCooperation $cooperation,
        int $currentRound,
        int $tokensUsed,
        float $loopSimilarity
    ): array;

    /**
     * Schładza układ kooperacji (Cooldown Reset), resetując temperaturę do wartości nominalnej 22.0°C.
     */
    public function cooldown(AgentCooperation $cooperation): AgentCooperation;
}

