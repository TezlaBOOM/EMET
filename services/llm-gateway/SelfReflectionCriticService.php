<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\SelfReflectionCriticInterface;

class SelfReflectionCriticService implements SelfReflectionCriticInterface
{
    public function inspectResponse(string $content, array $criteria = []): array
    {
        $trimmed = trim($content);
        $issues = [];
        $repairedContent = null;
        $score = 1.0;

        $expectJson = (bool) ($criteria['expect_json'] ?? false);
        $requiredKeys = is_array($criteria['required_keys'] ?? null) ? $criteria['required_keys'] : [];
        $requiredTerms = is_array($criteria['required_terms'] ?? null) ? $criteria['required_terms'] : [];

        if ($expectJson) {
            $cleanedJson = $this->extractJsonBlock($trimmed);
            $decoded = json_decode($cleanedJson, true);

            if (! is_array($decoded)) {
                $candidate = rtrim($cleanedJson, ", \t\n\r\0\x0B");
                if (str_starts_with($candidate, '{') && ! str_ends_with($candidate, '}')) {
                    $candidate .= '}';
                }
                $decoded = json_decode($candidate, true);
                if (is_array($decoded)) {
                    $repairedContent = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $candidate;
                } else {
                    $issues[] = 'Odpowiedź nie jest poprawnym obiektem JSON.';
                    $score -= 0.5;
                }
            } else {
                $repairedContent = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $cleanedJson;
            }

            if (is_array($decoded) && ! empty($requiredKeys)) {
                foreach ($requiredKeys as $reqKey) {
                    $keyStr = (string) $reqKey;
                    if (! array_key_exists($keyStr, $decoded)) {
                        $issues[] = "Brak wymaganego klucza JSON: {$keyStr}";
                        $decoded[$keyStr] = null;
                        $score -= 0.2;
                    }
                }
                $repairedContent = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $repairedContent;
            }
        }

        if (! empty($requiredTerms)) {
            $lower = mb_strtolower($trimmed);
            foreach ($requiredTerms as $term) {
                $termStr = (string) $term;
                if ($termStr !== '' && ! str_contains($lower, mb_strtolower($termStr))) {
                    $issues[] = "Brak wymaganej sekcji lub pojęcia: {$termStr}";
                    $score -= 0.2;
                }
            }
        }

        $finalScore = round(max(0.0, min(1.0, $score)), 4);

        return [
            'is_valid' => empty($issues),
            'score' => $finalScore,
            'issues' => $issues,
            'repaired_content' => $repairedContent,
        ];
    }

    private function extractJsonBlock(string $text): string
    {
        if (preg_match('/```(?:json)?\s*\n(.*?)```/is', $text, $m)) {
            return trim($m[1]);
        }

        return $text;
    }
}

