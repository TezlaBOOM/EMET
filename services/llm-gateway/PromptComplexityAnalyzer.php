<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\CompletionRequest;

class PromptComplexityAnalyzer
{
    /**
     * Słowa kluczowe wskazujące na wysoką złożoność (wnioskowanie, architektura, kodowanie, matematyka).
     *
     * @var list<string>
     */
    private const HIGH_COMPLEXITY_KEYWORDS = [
        'architektur', 'architecture', 'refaktoryz', 'refactor', 'algorytm', 'algorithm',
        'optymaliz', 'optimize', 'debug', 'security audit', 'audyt bezpieczeństwa',
        'wieloetap', 'multi-step', 'step by step', 'krok po kroku', 'udowodnij', 'prove',
        'implementuj', 'implement', 'zaprojektuj', 'design system', 'concurrency',
        'współbieżn', 'transakcj', 'sql query', 'migracj', 'vulnerability', 'analiza porównawcza',
    ];

    /**
     * Słowa kluczowe wskazujące na proste zadanie (klasyfikacja, streszczenie, tłumaczenie, formatowanie).
     *
     * @var list<string>
     */
    private const LOW_COMPLEXITY_KEYWORDS = [
        'podsumuj', 'summarize', 'streszcz', 'przetłumacz', 'translate', 'klasyfikuj', 'classify',
        'tak czy nie', 'yes or no', 'krótko', 'briefly', 'wyodrębnij', 'extract',
        'popraw literówki', 'fix typos', 'sformatuj', 'format as',
    ];

    /**
     * Ocenia złożoność zapytania LLM w skali 0.0 – 1.0 i przypisuje poziom: low | medium | high.
     *
     * @return array{
     *     score: float,
     *     tier: 'low'|'medium'|'high',
     *     signals: list<string>
     * }
     */
    public function analyze(CompletionRequest $request): array
    {
        $score = 0.20;
        $signals = [];

        $combinedUserText = '';
        $messageCount = 0;

        foreach ($request->messages as $msg) {
            $role = strtolower((string) ($msg['role'] ?? 'user'));
            $content = (string) ($msg['content'] ?? '');
            if ($role !== 'system') {
                $combinedUserText .= "\n".$content;
                $messageCount++;
            }
        }

        $textLower = mb_strtolower(trim($combinedUserText));
        $charLength = mb_strlen($textLower);

        // 1. Długość kontekstu i wieloturowość
        if ($charLength > 2500) {
            $score += 0.25;
            $signals[] = 'long_context';
        } elseif ($charLength > 800) {
            $score += 0.12;
            $signals[] = 'medium_context';
        } elseif ($charLength < 180) {
            $score -= 0.08;
            $signals[] = 'short_prompt';
        }

        if ($messageCount > 6) {
            $score += 0.10;
            $signals[] = 'deep_conversation_history';
        }

        // 2. Obecność bloków kodu lub struktur technicznych
        if (str_contains($textLower, '```') || preg_match('/\b(?:function|class|public\s+function|select\s+.*\s+from|def\s+\w+)\b/i', $combinedUserText) === 1) {
            $score += 0.28;
            $signals[] = 'code_or_sql_detected';
        }

        // 3. Wywołania narzędzi (Function Calling / Tools)
        if (! empty($request->tools)) {
            $score += 0.25;
            $signals[] = 'tools_attached';
        }

        // 4. Słowa kluczowe głębokiego wnioskowania
        $highMatches = 0;
        foreach (self::HIGH_COMPLEXITY_KEYWORDS as $kw) {
            if (str_contains($textLower, $kw)) {
                $highMatches++;
            }
        }
        if ($highMatches > 0) {
            $score += min(0.35, $highMatches * 0.14);
            $signals[] = 'reasoning_keywords';
        }

        // 5. Słowa kluczowe prostych zadań
        $lowMatches = 0;
        foreach (self::LOW_COMPLEXITY_KEYWORDS as $kw) {
            if (str_contains($textLower, $kw)) {
                $lowMatches++;
            }
        }
        if ($lowMatches > 0 && $highMatches === 0 && empty($request->tools)) {
            $score -= min(0.20, $lowMatches * 0.10);
            $signals[] = 'simple_task_keywords';
        }

        $normalizedScore = round(max(0.0, min(1.0, $score)), 2);

        $lowThreshold = (float) config('agenthub.llm.smart_routing.low_threshold', 0.35);
        $highThreshold = (float) config('agenthub.llm.smart_routing.high_threshold', 0.65);

        $tier = match (true) {
            $normalizedScore >= $highThreshold => 'high',
            $normalizedScore <= $lowThreshold => 'low',
            default => 'medium',
        };

        return [
            'score' => $normalizedScore,
            'tier' => $tier,
            'signals' => $signals,
        ];
    }
}

