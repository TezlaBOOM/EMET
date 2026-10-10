<?php

declare(strict_types=1);

namespace App\Contracts\Chat;

/**
 * Kontrakt orkiestratora konwersacji wieloagentowych (Multi-Agent Group Chat).
 */
interface GroupChatOrchestratorInterface
{
    /**
     * Inicjalizacja nowej rundy konwersacji po nadejściu wiadomości od użytkownika.
     *
     * @return array{round_id: string, turns_scheduled: int}
     */
    public function initiateRound(int $conversationId, string $userMessageContent): array;

    /**
     * Wyznaczenie kolejnego agenta, który powinien zabrać głos w oparciu o tryb orkiestracji
     * (mention, broadcast, round_robin, moderator).
     *
     * @return array{next_agent_id: ?int, reason: string, is_complete: bool}
     */
    public function determineNextSpeaker(int $conversationId, int $currentTurn): array;

    /**
     * Przygotowanie kontekstu promptu dla konkretnego agenta z uwzględnieniem
     * polityki dołączenia (join_context: full, summary, last_n, none) oraz trybu stateless.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function buildAgentContext(int $conversationId, int $agentId): array;

    /**
     * Weryfikacja limitów bezpieczeństwa (max_turns, detekcja powtórzeń treści, budżet kosztowy).
     *
     * @return array{allowed: bool, violation_reason: ?string}
     */
    public function validateTurnLimits(int $conversationId): array;

    /**
     * Wymuszone zatrzymanie bieżącej rundy lub całej konwersacji (przycisk Stop).
     */
    public function stopConversation(int $conversationId, ?int $specificAgentId = null): bool;
}
