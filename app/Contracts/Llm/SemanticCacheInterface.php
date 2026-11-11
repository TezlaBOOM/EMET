<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

interface SemanticCacheInterface
{
    /**
     * Sprawdza, czy dla danego zapytania istnieje semantycznie zbieżna odpowiedź w cache'u.
     */
    public function lookup(CompletionRequest $request): ?CompletionResponse;

    /**
     * Zapisuje odpowiedź modelu w semantycznej pamięci podręcznej.
     */
    public function store(CompletionRequest $request, CompletionResponse $response, ?int $ttlSeconds = null): void;

    /**
     * Czyści wpisy semantycznego cache'u (wszystkie lub dla wybranego modelu).
     */
    public function flush(?string $model = null): int;

    /**
     * Zwraca zagregowane statystyki trafień i oszczędności semantycznego cache'u.
     *
     * @return array{
     *     enabled: bool,
     *     entries_count: int,
     *     total_hits: int,
     *     saved_tokens: int,
     *     saved_cost_usd: float,
     *     similarity_threshold: float,
     *     ttl_seconds: int
     * }
     */
    public function getStats(): array;

    /**
     * Oblicza znormalizowany wektor semantyczny (embedding) dla tekstu.
     *
     * @return list<float>
     */
    public function computeEmbedding(string $text): array;

    /**
     * Oblicza podobieństwo kosinusowe dwóch wektorów.
     *
     * @param  list<float>  $vectorA
     * @param  list<float>  $vectorB
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float;
}

