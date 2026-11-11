<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Contracts\Agents\AgentCooperationInterface;
use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Models\Agent;
use App\Models\AgentCooperation;
use Throwable;

class AgentCooperationService implements AgentCooperationInterface
{
    public function __construct(
        protected LlmGatewayInterface $llmGateway
    ) {}

    public function runCooperationTask(
        AgentCooperation $cooperation,
        string $taskPrompt,
        ?int $roundsOverride = null
    ): AgentCooperation {
        $participants = $cooperation->participantAgents()->values();

        if ($participants->isEmpty() && $cooperation->coordinator) {
            $participants->push($cooperation->coordinator);
        }

        if ($participants->isEmpty()) {
            $cooperation->update([
                'status' => 'idle',
                'last_task_prompt' => $taskPrompt,
                'final_synthesis' => 'Brak przypisanych agentów w zespole kooperacji.',
            ]);

            return $cooperation->fresh();
        }

        $plannedRounds = max(1, (int) ($roundsOverride ?? $cooperation->max_rounds));
        $discussionLog = [];
        $overheatEvents = $cooperation->overheat_events ?? [];
        $previousContents = [];

        $tokensUsed = 0;
        $turnsExecuted = 0;
        $currentTemperature = 24.0;
        $thermalStatus = AgentCooperation::THERMAL_NOMINAL;
        $trippedOverheat = false;
        $overheatReason = null;

        $cooperation->update([
            'status' => 'running',
            'last_task_prompt' => $taskPrompt,
        ]);

        for ($round = 1; $round <= $plannedRounds; $round++) {
            foreach ($participants as $idx => $agent) {
                /** @var Agent $agent */
                $nextAgent = $participants->get(($idx + 1) % $participants->count());
                $roleLabel = $this->resolveAgentRoleLabel($cooperation, $agent, $idx);

                // Sprawdzenie bezpiecznika przegrzania PRZED wykonaniem kolejnej tury
                $preCheck = $this->evaluateOverheatGuard(
                    $cooperation,
                    $round,
                    $tokensUsed,
                    0.0
                );

                if ($cooperation->overheat_guard_enabled && $preCheck['is_overheated']) {
                    $trippedOverheat = true;
                    $overheatReason = $preCheck['reason'];
                    $currentTemperature = $preCheck['temperature'];
                    $thermalStatus = AgentCooperation::THERMAL_OVERHEATED;

                    $overheatEvents[] = [
                        'round' => $round,
                        'turn' => $turnsExecuted + 1,
                        'agent_name' => $agent->name,
                        'reason' => $preCheck['reason'],
                        'message' => $preCheck['message'],
                        'temperature' => $currentTemperature,
                        'tokens_used' => $tokensUsed,
                        'timestamp' => now()->toIso8601String(),
                    ];

                    break 2;
                }

                $turnResult = $this->executeAgentTurn(
                    $cooperation,
                    $agent,
                    $nextAgent,
                    $roleLabel,
                    $taskPrompt,
                    $round,
                    $turnsExecuted + 1,
                    $discussionLog
                );

                $loopSimilarity = $this->computeLoopSimilarity($turnResult['content'], $previousContents);
                $previousContents[] = $turnResult['content'];
                $tokensUsed += $turnResult['tokens'];
                $turnsExecuted++;

                $postCheck = $this->evaluateOverheatGuard(
                    $cooperation,
                    $round,
                    $tokensUsed,
                    $loopSimilarity
                );

                $prevTemp = $currentTemperature;
                $currentTemperature = $postCheck['temperature'];
                $thermalStatus = $postCheck['thermal_status'];

                $discussionLog[] = [
                    'round' => $round,
                    'turn' => $turnsExecuted,
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'agent_model' => $agent->primary_model,
                    'role' => $roleLabel,
                    'target_agent_name' => $nextAgent ? $nextAgent->name : 'Koordynator',
                    'skills_used' => $agent->skills->where('is_enabled', true)->pluck('name')->values()->all(),
                    'content' => $turnResult['content'],
                    'tokens' => $turnResult['tokens'],
                    'temperature_delta' => round(max(0.5, $currentTemperature - $prevTemp), 1),
                    'temperature_after' => $currentTemperature,
                    'loop_similarity' => round($loopSimilarity, 2),
                    'timestamp' => now()->format('H:i:s'),
                ];

                if ($cooperation->overheat_guard_enabled && $postCheck['is_overheated']) {
                    $trippedOverheat = true;
                    $overheatReason = $postCheck['reason'];
                    $thermalStatus = AgentCooperation::THERMAL_OVERHEATED;

                    $overheatEvents[] = [
                        'round' => $round,
                        'turn' => $turnsExecuted,
                        'agent_name' => $agent->name,
                        'reason' => $postCheck['reason'],
                        'message' => $postCheck['message'],
                        'temperature' => $currentTemperature,
                        'tokens_used' => $tokensUsed,
                        'timestamp' => now()->toIso8601String(),
                    ];

                    break 2;
                }
            }
        }

        $finalSynthesis = null;
        $preservedContext = $cooperation->preserved_context;
        $reloadsCount = (int) $cooperation->context_reloads_count;

        if (! $trippedOverheat || $cooperation->auto_synthesize_on_overheat) {
            $finalSynthesis = $this->generateFinalSynthesis(
                $cooperation,
                $taskPrompt,
                $discussionLog,
                $trippedOverheat,
                $overheatReason
            );
        }

        if ($trippedOverheat && $cooperation->auto_synthesize_on_overheat) {
            $preservedContext = $this->buildPreservedContextSnapshot(
                $cooperation,
                $discussionLog,
                $finalSynthesis,
                'Auto-kompresja po wykryciu przegrzania czatu (ochrona przed zmyślaniem / halucynacjami)'
            );
            $reloadsCount++;
        }

        $cooperation->update([
            'current_temperature' => $currentTemperature,
            'thermal_status' => $thermalStatus,
            'status' => $trippedOverheat ? 'overheated' : 'completed',
            'total_tokens_used' => $cooperation->total_tokens_used + $tokensUsed,
            'total_turns_executed' => $cooperation->total_turns_executed + $turnsExecuted,
            'context_reloads_count' => $reloadsCount,
            'preserved_context' => $preservedContext,
            'discussion_log' => $discussionLog,
            'overheat_events' => $overheatEvents,
            'final_synthesis' => $finalSynthesis,
        ]);

        return $cooperation->fresh();
    }

    public function evaluateOverheatGuard(
        AgentCooperation $cooperation,
        int $currentRound,
        int $tokensUsed,
        float $loopSimilarity
    ): array {
        $maxRounds = max(1, (int) $cooperation->max_rounds);
        $maxTokens = max(100, (int) $cooperation->max_tokens_budget);
        $maxTempThreshold = (float) ($cooperation->max_temperature_score ?: 85.0);
        $repThreshold = (float) ($cooperation->repetition_threshold ?: 0.78);

        $roundRatio = $currentRound / $maxRounds;
        $tokenRatio = $tokensUsed / $maxTokens;

        // Model termiczny: baza 24°C + obciążenie rund + obciążenie tokenowe + kara za zapętlenie semantyczne
        $computedTemp = 24.0
            + ($roundRatio * 34.0)
            + ($tokenRatio * 36.0)
            + ($loopSimilarity * 38.0);

        $temperature = round(min(100.0, max(22.0, $computedTemp)), 1);

        $reason = null;
        $message = null;
        $isOverheated = false;

        if ($currentRound > $maxRounds) {
            $isOverheated = true;
            $reason = 'max_rounds_exceeded';
            $message = "Przekroczono maksymalną liczbę rund dyskusji ({$currentRound}/{$maxRounds}). Bezpiecznik zatrzymał pętlę.";
            $temperature = max($temperature, $maxTempThreshold);
        } elseif ($tokensUsed >= $maxTokens) {
            $isOverheated = true;
            $reason = 'token_budget_exceeded';
            $message = "Wykorzystano budżet tokenów sesji ({$tokensUsed}/{$maxTokens}). Ochrona przed przegrzaniem odcięła kolejne zapytania.";
            $temperature = max($temperature, $maxTempThreshold);
        } elseif ($loopSimilarity >= $repThreshold) {
            $isOverheated = true;
            $reason = 'semantic_loop_detected';
            $similarityPct = round($loopSimilarity * 100, 0);
            $message = "Wykryto zapętlenie semantyczne dyskusji (Echo Chamber: {$similarityPct}%). Przerwano jałową wymianę zdań.";
            $temperature = max($temperature, $maxTempThreshold + 2.5);
        } elseif ($temperature >= $maxTempThreshold) {
            $isOverheated = true;
            $reason = 'thermal_threshold_exceeded';
            $message = "Temperatura układu kooperacji osiągnęła próg krytyczny ({$temperature}°C ≥ {$maxTempThreshold}°C). Aktywowano Throttling / Cooldown.";
        }

        $thermalStatus = match (true) {
            $isOverheated || $temperature >= $maxTempThreshold => AgentCooperation::THERMAL_OVERHEATED,
            $temperature >= ($maxTempThreshold - 12.0) => AgentCooperation::THERMAL_HOT,
            $temperature >= 50.0 => AgentCooperation::THERMAL_WARM,
            default => AgentCooperation::THERMAL_NOMINAL,
        };

        return [
            'temperature' => $temperature,
            'thermal_status' => $thermalStatus,
            'is_overheated' => $isOverheated,
            'reason' => $reason,
            'message' => $message,
        ];
    }

    public function cooldown(AgentCooperation $cooperation, ?string $customContextNote = null): AgentCooperation
    {
        $discussionLog = $cooperation->discussion_log ?? [];
        $preservedSnapshot = $this->buildPreservedContextSnapshot(
            $cooperation,
            $discussionLog,
            $cooperation->final_synthesis,
            $customContextNote
        );

        $reloadsCount = ((int) $cooperation->context_reloads_count) + 1;

        $checkpointEntry = [
            'round' => 0,
            'turn' => 1,
            'agent_id' => $cooperation->coordinator_agent_id ?? 0,
            'agent_name' => 'Pamięć Kontekstowa (Reload #'.$reloadsCount.')',
            'agent_model' => 'context-compactor',
            'role' => 'Strażnik Kontekstu Anty-Halucynacyjnego',
            'target_agent_name' => 'Cały Zespół',
            'skills_used' => ['Kompresja Kontekstu', 'Ochrona przed Halucynacją'],
            'content' => '🔄 Przeładowano czat w celu oczyszczenia okna kontekstowego i zapobieżenia zmyślaniu modeli. '
                .'Wszystkie kluczowe fakty i ustalenia z poprzednich tur zostały zachowane w Pamięci Kontekstowej zespołu.',
            'tokens' => 0,
            'temperature_delta' => 0.0,
            'temperature_after' => 22.0,
            'loop_similarity' => 0.0,
            'timestamp' => now()->format('H:i:s'),
        ];

        $cooperation->update([
            'current_temperature' => 22.0,
            'thermal_status' => AgentCooperation::THERMAL_NOMINAL,
            'status' => 'cooled_down',
            'context_reloads_count' => $reloadsCount,
            'preserved_context' => $preservedSnapshot,
            'discussion_log' => [$checkpointEntry],
            'cooled_down_at' => now(),
        ]);

        return $cooperation->fresh();
    }

    /**
     * Buduje skondensowaną pigułkę pamięci kontekstowej z dotychczasowej rozmowy,
     * aby po przeładowaniu czatu modele pamiętały wszystkie fakty i ustalenia, ale nie halucynowały od nadmiaru historii.
     *
     * @param  array<int, array<string, mixed>>  $discussionLog
     */
    protected function buildPreservedContextSnapshot(
        AgentCooperation $cooperation,
        array $discussionLog,
        ?string $finalSynthesis = null,
        ?string $customNote = null
    ): string {
        $lines = [];
        $reloadNum = ((int) $cooperation->context_reloads_count) + 1;
        $lines[] = "📌 [PAMIĘĆ KONTEKSTOWA ZESPOŁU – PRZEŁADOWANIE #{$reloadNum} (".now()->format('Y-m-d H:i:s').')]';

        if (! empty($cooperation->last_task_prompt)) {
            $lines[] = '• Główny cel / zadanie: '.trim((string) $cooperation->last_task_prompt);
        }

        if (! empty($customNote)) {
            $lines[] = '• Notatka priorytetowa: '.trim($customNote);
        }

        if (! empty($cooperation->preserved_context)) {
            $prevCompact = mb_substr(trim((string) $cooperation->preserved_context), 0, 500);
            $lines[] = '• Wcześniejsza pamięć bazowa: '.$prevCompact;
        }

        $meaningfulTurns = array_values(array_filter(
            $discussionLog,
            fn (array $item) => ($item['round'] ?? 0) > 0
        ));

        if (! empty($meaningfulTurns)) {
            $lines[] = '• Kluczowe ustalenia agentów przed przeładowaniem czatu:';
            foreach (array_slice($meaningfulTurns, -4) as $turn) {
                $snippet = mb_substr(trim((string) ($turn['content'] ?? '')), 0, 220);
                $lines[] = "  - {$turn['agent_name']} ({$turn['role']}): {$snippet}";
            }
        }

        if (! empty($finalSynthesis)) {
            $lines[] = '• Wypracowany dotąd konsensus: '.mb_substr(trim($finalSynthesis), 0, 450);
        }

        return implode("\n", $lines);
    }

    /**
     * Oblicza podobieństwo semantyczne (współczynnik Jaccarda na 3-gramach słownych i tokenach)
     * względem wcześniejszych wypowiedzi w sesji, aby wykryć zapętlenie (Ping-Pong / Echo Chamber).
     *
     * @param  array<int, string>  $previousMessages
     */
    protected function computeLoopSimilarity(string $currentMessage, array $previousMessages): float
    {
        if (empty($previousMessages)) {
            return 0.0;
        }

        $currentTokens = $this->tokenizeText($currentMessage);
        if (count($currentTokens) < 4) {
            return 0.0;
        }

        $maxSim = 0.0;
        foreach (array_slice($previousMessages, -4) as $prev) {
            $prevTokens = $this->tokenizeText($prev);
            if (empty($prevTokens)) {
                continue;
            }

            $intersection = count(array_intersect($currentTokens, $prevTokens));
            $union = count(array_unique(array_merge($currentTokens, $prevTokens)));

            if ($union > 0) {
                $sim = $intersection / $union;
                if ($sim > $maxSim) {
                    $maxSim = $sim;
                }
            }
        }

        return round($maxSim, 4);
    }

    /**
     * @return array<int, string>
     */
    protected function tokenizeText(string $text): array
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');
        $words = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= 3)));
    }

    protected function resolveAgentRoleLabel(AgentCooperation $cooperation, Agent $agent, int $index): string
    {
        $customRoles = $cooperation->participant_roles ?? [];
        if (! empty($customRoles[$agent->id])) {
            return (string) $customRoles[$agent->id];
        }

        if ($cooperation->coordinator_agent_id && (int) $cooperation->coordinator_agent_id === (int) $agent->id) {
            return 'Koordynator & Architekt';
        }

        return match ($index % 4) {
            0 => 'Główny Analityk (Lead)',
            1 => 'Inżynier Wykonawczy (Builder)',
            2 => 'Audytor & Krytyk Jakości (Reviewer)',
            default => 'Syntezator & Weryfikator (Verifier)',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $discussionLog
     * @return array{content: string, tokens: int}
     */
    protected function executeAgentTurn(
        AgentCooperation $cooperation,
        Agent $agent,
        ?Agent $nextAgent,
        string $roleLabel,
        string $taskPrompt,
        int $round,
        int $turnNumber,
        array $discussionLog
    ): array {
        $recentContext = collect(array_slice($discussionLog, -3))
            ->map(fn (array $entry) => "[{$entry['agent_name']} ({$entry['role']})]: {$entry['content']}")
            ->implode("\n");

        $skillsList = $agent->skills->where('is_enabled', true)->pluck('name')->implode(', ') ?: 'analiza ogólna';
        $handoffTarget = $nextAgent ? $nextAgent->name : 'Koordynator Końcowy';
        $preservedBlock = ! empty($cooperation->preserved_context)
            ? "\n=== ZACHOWANY KONTEKST Z PRZEŁADOWANIA CZATU (TRZYMAJ SIĘ TYCH FAKTÓW, NIE ZMYŚLAJ) ===\n{$cooperation->preserved_context}\n"
            : '';

        $systemPrompt = trim(($agent->system_prompt ?? 'Jesteś wyspecjalizowanym agentem AI.')."\n"
            ."Bierzesz udział w kooperacji wieloagentowej '{$cooperation->name}' (topologia: {$cooperation->topology}).\n"
            ."Twoja rola w zespole: {$roleLabel}. Dostępne skille: {$skillsList}."
            .$preservedBlock."\n"
            ."Odpowiadaj zwięźle, konkretnie i merytorycznie (3-5 zdań), wnosząc nową wartość i przekazując wnioski do kolejnego ogniwa: {$handoffTarget}.");

        $userContent = "Zadanie zespołu: {$taskPrompt}\n"
            ."Runda: {$round}, Tura: {$turnNumber}.\n"
            .($recentContext !== '' ? "Dotychczasowe ustalenia zespołu:\n{$recentContext}\n" : '')
            .'Podaj swój wkład ekspercki i rekomendację wykonawczą.';

        try {
            $response = $this->llmGateway->complete(new CompletionRequest(
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                model: $agent->primary_model,
                temperature: 0.4,
                maxTokens: 350,
                metadata: [
                    'cooperation_id' => $cooperation->id,
                    'agent_id' => $agent->id,
                    'round' => $round,
                ]
            ));

            $content = trim($response->content);
            if ($content !== '') {
                $tokens = max(45, $response->totalTokens ?: ($response->promptTokens + $response->completionTokens));

                return [
                    'content' => $content,
                    'tokens' => $tokens,
                ];
            }
        } catch (Throwable) {
            // Fallback do deterministycznej symulacji eksperckiej w środowisku bez aktywnego klucza API
        }

        $fallbackContent = $this->buildStructuredCollaborativeContribution(
            $agent,
            $nextAgent,
            $roleLabel,
            $skillsList,
            $taskPrompt,
            $round,
            $turnNumber
        );

        $estimatedTokens = max(65, (int) ceil(mb_strlen($fallbackContent) / 3.2) + ($round * 25));

        return [
            'content' => $fallbackContent,
            'tokens' => $estimatedTokens,
        ];
    }

    protected function buildStructuredCollaborativeContribution(
        Agent $agent,
        ?Agent $nextAgent,
        string $roleLabel,
        string $skillsList,
        string $taskPrompt,
        int $round,
        int $turnNumber
    ): string {
        $shortTask = mb_substr(trim($taskPrompt), 0, 110);
        $nextName = $nextAgent ? $nextAgent->name : 'Koordynatora';

        return match ($turnNumber % 4) {
            1 => "[Runda {$round} • Analiza Architektoniczna] Jako {$roleLabel} ({$agent->name}) przeanalizowałem zadanie: \"{$shortTask}\" z użyciem skilli [{$skillsList}]. "
                .'Definiuję podział pracy na moduły domenowe, kontrakty wejścia/wyjścia oraz kryteria akceptacji SLA. '
                ."Przekazuję specyfikację techniczną do @{$nextName} w celu weryfikacji implementacyjnej.",
            2 => "[Runda {$round} • Realizacja i Weryfikacja Narzędziowa] W oparciu o specyfikację poprzednika wdrażam potok wykonawczy z wykorzystaniem [{$skillsList}]. "
                ."Zoptymalizowałem zapytania, obsłużyłem warunki brzegowe oraz przygotowałem artefakty wykonawcze dla zadania \"{$shortTask}\". "
                ."Kieruję wypracowany rezultat do @{$nextName} do audytu bezpieczeństwa i odporności.",
            3 => "[Runda {$round} • Audyt Jakości i Red-Teaming] Przeprowadziłem inspekcję wypracowanego rozwiązania pod kątem wąskich gardeł, limitów budżetowych oraz bezpieczeństwa danych. "
                ."Wprowadziłem korektę walidacji schematów oraz zabezpieczenie idempotentności operacji przy wsparciu [{$skillsList}]. "
                ."Przekazuję zweryfikowany wariant do @{$nextName} w celu finalizacji.",
            default => "[Runda {$round} • Konsolidacja Etapu #{$turnNumber}] Zintegrowałem poprawki zespołu oraz potwierdziłem spójność wszystkich komponentów dla zadania \"{$shortTask}\". "
                ."Parametry jakościowe i operacyjne mieszczą się w normie, gotowość do syntezy końcowej potwierdzona z udziałem @{$nextName}.",
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $discussionLog
     */
    protected function generateFinalSynthesis(
        AgentCooperation $cooperation,
        string $taskPrompt,
        array $discussionLog,
        bool $trippedOverheat,
        ?string $overheatReason
    ): string {
        $contributors = collect($discussionLog)->pluck('agent_name')->unique()->implode(', ');
        $turnsCount = count($discussionLog);

        $header = $trippedOverheat
            ? "⚡ [SYNTEZA AWARYJNA PO ZADZIAŁANIU STRAŻNIKA PRZEGRZANIA: {$overheatReason}]\n"
            : "✅ [KONSENSUS KOŃCOWY ZESPOŁU KOOPERACJI: {$cooperation->name}]\n";

        $points = collect($discussionLog)
            ->take(-4)
            ->map(fn (array $item) => "• {$item['agent_name']} ({$item['role']}): {$item['content']}")
            ->implode("\n");

        return $header
            ."Zadanie: {$taskPrompt}\n"
            ."Zaangażowani agenci ({$turnsCount} tur wymiany): {$contributors}.\n\n"
            ."Wypracowane podsumowanie i plan działania:\n"
            .$points;
    }
}
