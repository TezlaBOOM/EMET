<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\CompletionResponse;
use App\Contracts\Llm\SemanticCacheInterface;
use App\Models\LlmSemanticCacheEntry;
use App\Models\ModelPricing;
use Illuminate\Support\Facades\Schema;

class SemanticCacheService implements SemanticCacheInterface
{
    private const VECTOR_DIMENSIONS = 128;

    public function isEnabled(CompletionRequest $request): bool
    {
        if (! empty($request->metadata['skip_cache'])) {
            return false;
        }

        if (isset($request->metadata['use_semantic_cache'])) {
            return (bool) $request->metadata['use_semantic_cache'];
        }

        return (bool) config('agenthub.llm.semantic_cache.enabled', false);
    }

    public function lookup(CompletionRequest $request): ?CompletionResponse
    {
        if (! $this->isEnabled($request)) {
            return null;
        }

        $maxTemp = (float) config('agenthub.llm.semantic_cache.max_temperature', 0.7);
        if ($request->temperature > $maxTemp) {
            return null;
        }

        if (! Schema::hasTable('llm_semantic_cache_entries')) {
            return null;
        }

        $startTime = microtime(true);
        [$systemHash, $userPrompt] = $this->extractHashesAndPrompt($request);

        if ($userPrompt === '') {
            return null;
        }

        $targetModel = $this->normalizeModelKey($request->model);
        $exactKey = hash('sha256', "{$targetModel}|{$systemHash}|".$this->normalizeText($userPrompt));
        $threshold = (float) ($request->metadata['semantic_cache_threshold']
            ?? config('agenthub.llm.semantic_cache.similarity_threshold', 0.95));

        // 1. Szybkie sprawdzenie dokładnego klucza znormalizowanego (O(1))
        $exactEntry = LlmSemanticCacheEntry::query()
            ->where('cache_key', $exactKey)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($exactEntry !== null) {
            return $this->registerHit($exactEntry, 1.0, $startTime);
        }

        // 2. Wyszukiwanie semantyczne po wektorach dla tego samego modelu i system promptu
        $candidates = LlmSemanticCacheEntry::query()
            ->where('model', $targetModel)
            ->where('system_prompt_hash', $systemHash)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('updated_at')
            ->limit(200)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $queryVector = $this->computeEmbedding($userPrompt);
        $bestEntry = null;
        $bestSimilarity = 0.0;

        foreach ($candidates as $candidate) {
            $candidateVector = $candidate->prompt_embedding;
            if (! is_array($candidateVector) || empty($candidateVector)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($queryVector, $candidateVector);
            if ($similarity >= $threshold && $similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestEntry = $candidate;
            }
        }

        if ($bestEntry === null) {
            return null;
        }

        return $this->registerHit($bestEntry, $bestSimilarity, $startTime);
    }

    public function store(CompletionRequest $request, CompletionResponse $response, ?int $ttlSeconds = null): void
    {
        if (! $this->isEnabled($request)) {
            return;
        }

        $maxTemp = (float) config('agenthub.llm.semantic_cache.max_temperature', 0.7);
        if ($request->temperature > $maxTemp) {
            return;
        }

        if (! Schema::hasTable('llm_semantic_cache_entries')) {
            return;
        }

        [$systemHash, $userPrompt] = $this->extractHashesAndPrompt($request);
        if ($userPrompt === '' || trim($response->content) === '') {
            return;
        }

        $targetModel = $this->normalizeModelKey($request->model ?: $response->model);
        $exactKey = hash('sha256', "{$targetModel}|{$systemHash}|".$this->normalizeText($userPrompt));
        $ttl = $ttlSeconds ?? (int) config('agenthub.llm.semantic_cache.ttl_seconds', 86400);
        $embedding = $this->computeEmbedding($userPrompt);

        LlmSemanticCacheEntry::updateOrCreate(
            ['cache_key' => $exactKey],
            [
                'model' => $targetModel,
                'system_prompt_hash' => $systemHash,
                'prompt_text' => mb_substr($userPrompt, 0, 10000),
                'prompt_embedding' => $embedding,
                'response_content' => $response->content,
                'tool_calls' => $response->toolCalls,
                'prompt_tokens' => $response->promptTokens,
                'completion_tokens' => $response->completionTokens,
                'expires_at' => $ttl > 0 ? now()->addSeconds($ttl) : null,
            ]
        );
    }

    public function flush(?string $model = null): int
    {
        if (! Schema::hasTable('llm_semantic_cache_entries')) {
            return 0;
        }

        $query = LlmSemanticCacheEntry::query();
        if ($model !== null && $model !== '') {
            $query->where('model', $this->normalizeModelKey($model));
        }

        return $query->delete();
    }

    public function getStats(): array
    {
        $enabled = (bool) config('agenthub.llm.semantic_cache.enabled', false);
        $threshold = (float) config('agenthub.llm.semantic_cache.similarity_threshold', 0.95);
        $ttl = (int) config('agenthub.llm.semantic_cache.ttl_seconds', 86400);

        if (! Schema::hasTable('llm_semantic_cache_entries')) {
            return [
                'enabled' => $enabled,
                'entries_count' => 0,
                'total_hits' => 0,
                'saved_tokens' => 0,
                'saved_cost_usd' => 0.0,
                'similarity_threshold' => $threshold,
                'ttl_seconds' => $ttl,
            ];
        }

        return [
            'enabled' => $enabled,
            'entries_count' => (int) LlmSemanticCacheEntry::count(),
            'total_hits' => (int) LlmSemanticCacheEntry::sum('hit_count'),
            'saved_tokens' => (int) LlmSemanticCacheEntry::sum('saved_tokens'),
            'saved_cost_usd' => round((float) LlmSemanticCacheEntry::sum('saved_cost_usd'), 6),
            'similarity_threshold' => $threshold,
            'ttl_seconds' => $ttl,
        ];
    }

    public function computeEmbedding(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $vector = array_fill(0, self::VECTOR_DIMENSIONS, 0.0);

        if ($normalized === '') {
            return $vector;
        }

        // 1. Tokeny słowne i rdzenie słów (waga 2.0)
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            $stem = mb_substr($word, 0, max(3, mb_strlen($word) - 1));
            $this->accumulateFeature($vector, 'w:'.$stem, 2.0);
            $this->accumulateFeature($vector, 'f:'.$word, 1.5);
        }

        // 2. Znakowe 3-gramy (odporność na odmianę fleksyjną i literówki, waga 1.0)
        $padded = ' '.$normalized.' ';
        $len = mb_strlen($padded);
        for ($i = 0; $i <= $len - 3; $i++) {
            $trigram = mb_substr($padded, $i, 3);
            $this->accumulateFeature($vector, 'c3:'.$trigram, 1.0);
        }

        // 3. Normalizacja L2 wektora do długości jednostkowej
        $normSq = 0.0;
        foreach ($vector as $val) {
            $normSq += $val * $val;
        }

        if ($normSq > 0.0) {
            $norm = sqrt($normSq);
            foreach ($vector as $idx => $val) {
                $vector[$idx] = round($val / $norm, 6);
            }
        }

        return $vector;
    }

    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dim = min(count($vectorA), count($vectorB));
        if ($dim === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $dim; $i++) {
            $a = (float) $vectorA[$i];
            $b = (float) $vectorB[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($normA) * sqrt($normB))));
    }

    /**
     * @param  list<float>  $vector
     */
    private function accumulateFeature(array &$vector, string $feature, float $weight): void
    {
        $hash = crc32($feature);
        $index = abs($hash) % self::VECTOR_DIMENSIONS;
        $sign = (($hash >> 31) & 1) === 1 ? -1.0 : 1.0;
        $vector[$index] += $sign * $weight;
    }

    private function registerHit(LlmSemanticCacheEntry $entry, float $similarity, float $startTime): CompletionResponse
    {
        $totalSavedTokens = $entry->prompt_tokens + $entry->completion_tokens;
        $savedCost = ModelPricing::calculateCost($entry->model, $entry->prompt_tokens, $entry->completion_tokens);

        $entry->update([
            'hit_count' => $entry->hit_count + 1,
            'saved_tokens' => $entry->saved_tokens + $totalSavedTokens,
            'saved_cost_usd' => $entry->saved_cost_usd + $savedCost,
            'last_hit_at' => now(),
        ]);

        $latencyMs = max(1, (int) round((microtime(true) - $startTime) * 1000));

        return new CompletionResponse(
            content: $entry->response_content,
            model: $entry->model,
            promptTokens: $entry->prompt_tokens,
            completionTokens: $entry->completion_tokens,
            totalTokens: $totalSavedTokens,
            latencyMs: $latencyMs,
            ttftMs: $latencyMs,
            accountId: null,
            estimatedCost: 0.0,
            toolCalls: $entry->tool_calls ?? [],
            raw: [
                'cache_hit' => true,
                'similarity' => round($similarity, 4),
                'cache_entry_id' => $entry->id,
                'saved_cost_usd' => $savedCost,
            ],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractHashesAndPrompt(CompletionRequest $request): array
    {
        $systemMessages = [];
        $conversationParts = [];

        foreach ($request->messages as $msg) {
            $role = strtolower((string) ($msg['role'] ?? 'user'));
            $content = trim((string) ($msg['content'] ?? ''));

            if ($role === 'system') {
                $systemMessages[] = $content;
            } else {
                $conversationParts[] = "{$role}: {$content}";
            }
        }

        $systemHash = hash('sha256', implode("\n", $systemMessages));
        $userPrompt = implode("\n", $conversationParts);

        return [$systemHash, $userPrompt];
    }

    private function normalizeText(string $text): string
    {
        $lower = mb_strtolower(trim($text));
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
    }

    private function normalizeModelKey(?string $model): string
    {
        return strtolower(trim($model ?: 'default'));
    }
}

