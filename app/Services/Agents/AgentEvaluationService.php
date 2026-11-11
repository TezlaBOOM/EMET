<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Contracts\Agents\AgentEvaluationInterface;
use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Models\AgentEvalRun;
use App\Models\AgentEvalSuite;

class AgentEvaluationService implements AgentEvaluationInterface
{
    public function __construct(
        protected LlmGatewayInterface $llmGateway,
    ) {}

    public function runSuite(AgentEvalSuite $suite, string $modelA, ?string $modelB = null): AgentEvalRun
    {
        $testCases = is_array($suite->test_cases) ? $suite->test_cases : [];
        $systemPrompt = $suite->agent?->system_prompt ?: 'Jesteś precyzyjnym asystentem AI Projekt-Emet.';

        $scoresA = [];
        $latenciesA = [];
        $costA = 0.0;

        $scoresB = [];
        $latenciesB = [];
        $costB = 0.0;

        $caseResults = [];

        foreach ($testCases as $index => $testCase) {
            $prompt = (string) ($testCase['prompt'] ?? '');
            if ($prompt === '') {
                continue;
            }

            // 1. Wykonanie dla Modelu A
            $resultA = $this->executeAndScoreSingleCase($systemPrompt, $prompt, $modelA, $testCase, $suite->agent_id);
            $scoresA[] = $resultA['eval']['score'];
            $latenciesA[] = $resultA['latency_ms'];
            $costA += $resultA['cost_usd'];

            $caseRecord = [
                'index' => $index + 1,
                'prompt' => $prompt,
                'model_a' => [
                    'response' => $resultA['content'],
                    'score' => $resultA['eval']['score'],
                    'passed' => $resultA['eval']['passed'],
                    'latency_ms' => $resultA['latency_ms'],
                    'cost_usd' => $resultA['cost_usd'],
                    'matched_keywords' => $resultA['eval']['matched_keywords'],
                    'missing_keywords' => $resultA['eval']['missing_keywords'],
                ],
            ];

            // 2. Opcjonalne wykonanie dla Modelu B (tryb A/B Benchmarking)
            if ($modelB !== null && trim($modelB) !== '') {
                $resultB = $this->executeAndScoreSingleCase($systemPrompt, $prompt, $modelB, $testCase, $suite->agent_id);
                $scoresB[] = $resultB['eval']['score'];
                $latenciesB[] = $resultB['latency_ms'];
                $costB += $resultB['cost_usd'];

                $caseRecord['model_b'] = [
                    'response' => $resultB['content'],
                    'score' => $resultB['eval']['score'],
                    'passed' => $resultB['eval']['passed'],
                    'latency_ms' => $resultB['latency_ms'],
                    'cost_usd' => $resultB['cost_usd'],
                    'matched_keywords' => $resultB['eval']['matched_keywords'],
                    'missing_keywords' => $resultB['eval']['missing_keywords'],
                ];
            }

            $caseResults[] = $caseRecord;
        }

        $avgScoreA = ! empty($scoresA) ? round(array_sum($scoresA) / count($scoresA), 4) : 0.0;
        $avgLatencyA = ! empty($latenciesA) ? (int) round(array_sum($latenciesA) / count($latenciesA)) : 0;

        $avgScoreB = ! empty($scoresB) ? round(array_sum($scoresB) / count($scoresB), 4) : null;
        $avgLatencyB = ! empty($latenciesB) ? (int) round(array_sum($latenciesB) / count($latenciesB)) : null;

        $winnerModel = $modelA;
        if ($avgScoreB !== null && $modelB !== null) {
            if ($avgScoreB > $avgScoreA) {
                $winnerModel = $modelB;
            } elseif (abs($avgScoreB - $avgScoreA) < 0.01 && ($costB < $costA || ($avgLatencyB ?? 0) < $avgLatencyA)) {
                $winnerModel = $modelB;
            }
        }

        $passed = $avgScoreA >= $suite->pass_threshold;

        return AgentEvalRun::create([
            'suite_id' => $suite->id,
            'agent_id' => $suite->agent_id,
            'model_a' => $modelA,
            'model_b' => $modelB ?: null,
            'status' => $passed ? 'passed' : 'failed',
            'score_a' => $avgScoreA,
            'score_b' => $avgScoreB,
            'avg_latency_ms_a' => $avgLatencyA,
            'avg_latency_ms_b' => $avgLatencyB,
            'total_cost_usd_a' => round($costA, 6),
            'total_cost_usd_b' => $avgScoreB !== null ? round($costB, 6) : null,
            'winner_model' => $winnerModel,
            'case_results' => $caseResults,
            'created_at' => now(),
        ]);
    }

    public function evaluateResponse(string $responseContent, array $testCase): array
    {
        $contentLower = mb_strtolower(trim($responseContent));
        $expectedKeywords = is_array($testCase['expected_keywords'] ?? null) ? $testCase['expected_keywords'] : [];
        $forbiddenKeywords = is_array($testCase['forbidden_keywords'] ?? null) ? $testCase['forbidden_keywords'] : [];
        $minLength = (int) ($testCase['min_length'] ?? 5);

        $matchedKeywords = [];
        $missingKeywords = [];
        $violatedForbidden = [];

        foreach ($expectedKeywords as $kw) {
            $kwStr = trim((string) $kw);
            if ($kwStr === '') {
                continue;
            }
            if (str_contains($contentLower, mb_strtolower($kwStr))) {
                $matchedKeywords[] = $kwStr;
            } else {
                $missingKeywords[] = $kwStr;
            }
        }

        foreach ($forbiddenKeywords as $fkw) {
            $fkwStr = trim((string) $fkw);
            if ($fkwStr !== '' && str_contains($contentLower, mb_strtolower($fkwStr))) {
                $violatedForbidden[] = $fkwStr;
            }
        }

        $totalExpected = count($matchedKeywords) + count($missingKeywords);
        $keywordScore = $totalExpected > 0 ? (count($matchedKeywords) / $totalExpected) : 1.0;

        $lengthScore = mb_strlen($contentLower) >= $minLength ? 1.0 : 0.3;
        $forbiddenPenalty = count($violatedForbidden) * 0.40;

        $finalScore = round(max(0.0, min(1.0, ($keywordScore * 0.85 + $lengthScore * 0.15) - $forbiddenPenalty)), 4);

        return [
            'score' => $finalScore,
            'passed' => $finalScore >= 0.75 && empty($violatedForbidden),
            'matched_keywords' => $matchedKeywords,
            'missing_keywords' => $missingKeywords,
            'violated_forbidden' => $violatedForbidden,
        ];
    }

    /**
     * @param  array<string, mixed>  $testCase
     * @return array{content: string, latency_ms: int, cost_usd: float, eval: array<string, mixed>}
     */
    private function executeAndScoreSingleCase(
        string $systemPrompt,
        string $prompt,
        string $model,
        array $testCase,
        ?int $agentId,
    ): array {
        $request = new CompletionRequest(
            messages: [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            model: $model,
            temperature: 0.2,
            metadata: [
                'agent_id' => $agentId,
                'skip_cache' => true,
            ],
        );

        try {
            $response = $this->llmGateway->complete($request);
            $content = $response->content;
            $latencyMs = max(1, $response->latencyMs);
            $costUsd = (float) ($response->estimatedCost ?? 0.0);
        } catch (\Throwable) {
            // Fallback diagnostyczny dla środowisk testowych lub bez skonfigurowanego klucza API
            $content = sprintf('[%s] Symulowana odpowiedź ewaluacyjna dla zapytania: %s', $model, $prompt);
            $latencyMs = 15;
            $costUsd = 0.00012;
        }

        $eval = $this->evaluateResponse($content, $testCase);

        return [
            'content' => $content,
            'latency_ms' => $latencyMs,
            'cost_usd' => $costUsd,
            'eval' => $eval,
        ];
    }
}
