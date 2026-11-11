<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

interface AiGuardrailsInterface
{
    /**
     * Przetwarza wyjściowe zapytanie przed wysłaniem do dostawcy LLM:
     * - weryfikuje brak prób Prompt Injection / Jailbreak,
     * - pseudonimizuje dane wrażliwe PII (PESEL, e-mail, karty płatnicze, IBAN, klucze API, telefony)
     *   zwracając zmodyfikowany CompletionRequest oraz mapę tokenów zastępczych.
     *
     * @return array{
     *     request: CompletionRequest,
     *     replacements: array<string, string>,
     *     matched_dlp_rules: list<string>,
     *     jailbreak_detected: bool,
     *     jailbreak_rules: list<string>
     * }
     */
    public function inspectAndMaskRequest(CompletionRequest $request, bool $isLocalModel = false): array;

    /**
     * Przywraca oryginalne wartości w odpowiedzi modelu na podstawie mapy pseudonimizacji DLP
     * oraz filtruje ewentualne wycieki w treści wyjściowej.
     *
     * @param  array<string, string>  $replacements
     */
    public function inspectAndRestoreResponse(CompletionResponse $response, array $replacements = []): CompletionResponse;

    /**
     * Zwraca statystyki działania bramki bezpieczeństwa AI Guardrails & DLP.
     *
     * @return array{
     *     enabled: bool,
     *     dlp_enabled: bool,
     *     jailbreak_action: string,
     *     blocked_count: int,
     *     masked_requests_count: int,
     *     total_masked_items: int
     * }
     */
    public function getStats(): array;
}

