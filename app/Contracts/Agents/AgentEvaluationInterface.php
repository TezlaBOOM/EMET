<?php

declare(strict_types=1);

namespace App\Contracts\Agents;

use App\Models\AgentEvalRun;
use App\Models\AgentEvalSuite;

interface AgentEvaluationInterface
{
    /**
     * Uruchamia zestaw testowy (Golden Dataset) dla wybranego modelu A oraz opcjonalnie modelu B (benchmark A/B).
     */
    public function runSuite(AgentEvalSuite $suite, string $modelA, ?string $modelB = null): AgentEvalRun;

    /**
     * Ocenia pojedynczą odpowiedź modelu względem kryteriów oczekiwanego przypadku testowego.
     *
     * @param  array{
     *     prompt: string,
     *     expected_keywords?: list<string>,
     *     forbidden_keywords?: list<string>,
     *     min_length?: int
     * }  $testCase
     * @return array{
     *     score: float,
     *     passed: bool,
     *     matched_keywords: list<string>,
     *     missing_keywords: list<string>,
     *     violated_forbidden: list<string>
     * }
     */
    public function evaluateResponse(string $responseContent, array $testCase): array;
}

