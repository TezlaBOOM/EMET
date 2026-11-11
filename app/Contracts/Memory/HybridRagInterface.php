<?php

declare(strict_types=1);

namespace App\Contracts\Memory;

use App\Models\MemoryChunk;
use App\Models\MemoryCollection;

interface HybridRagInterface
{
    /**
     * Ekstrahuje encje i relacje z fragmentu wiedzy oraz zapisuje je w Grafie Wiedzy (`memory_entities`, `memory_relations`).
     *
     * @return array{entities_count: int, relations_count: int, entities: list<string>}
     */
    public function extractAndStoreGraph(MemoryCollection $collection, MemoryChunk $chunk): array;

    /**
     * Wykonuje hybrydowe wyszukiwanie RAG łączące Dense Vector Search, pełnotekstowy BM25,
     * ekspansję Grafu Wiedzy (GraphRAG 1-hop) oraz Cross-Encoder Reranker.
     *
     * @return list<array{
     *     chunk: ?MemoryChunk,
     *     score: float,
     *     dense_score: float,
     *     bm25_score: float,
     *     graph_score: float,
     *     matched_entities: list<string>,
     *     payload: array<string, mixed>
     * }>
     */
    public function search(MemoryCollection $collection, string $query, int $limit = 5, string $mode = 'hybrid'): array;

    /**
     * Oblicza wynik BM25 / pokrycia leksykalnego dla zapytania względem treści dokumentu.
     */
    public function computeBm25Score(string $query, string $title, string $content): float;

    /**
     * Przeprowadza reranking kandydatów metodą Cross-Encoder (fuzja cech semantycznych, leksykalnych, bliskości fraz i grafu).
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public function rerank(string $query, array $candidates, int $limit = 5): array;
}

