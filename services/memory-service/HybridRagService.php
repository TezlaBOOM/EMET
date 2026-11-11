<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Llm\EmbeddingRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Memory\HybridRagInterface;
use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryChunk;
use App\Models\MemoryCollection;
use App\Models\MemoryEntity;
use App\Models\MemoryRelation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HybridRagService implements HybridRagInterface
{
    /**
     * Stop-słowa PL i EN pomijane przy ekstrakcji encji i obliczaniu BM25.
     *
     * @var list<string>
     */
    private const STOP_WORDS = [
        'oraz', 'jest', 'jako', 'przez', 'przy', 'albo', 'tylko', 'który', 'która', 'które',
        'tego', 'kiedy', 'gdzie', 'żeby', 'może', 'będzie', 'być', 'dla', 'czy', 'jak', 'nie',
        'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'will', 'are', 'was', 'into',
    ];

    public function __construct(
        protected LlmGatewayInterface $llmGateway,
        protected QdrantVectorStore $qdrantStore,
        protected PgVectorStore $pgVectorStore,
    ) {}

    public function getStore(MemoryCollection $collection): VectorStoreInterface
    {
        return match (strtolower($collection->vector_store)) {
            'pgvector' => $this->pgVectorStore,
            default => $this->qdrantStore,
        };
    }

    public function extractAndStoreGraph(MemoryCollection $collection, MemoryChunk $chunk): array
    {
        if (! Schema::hasTable('memory_entities') || ! Schema::hasTable('memory_relations')) {
            return ['entities_count' => 0, 'relations_count' => 0, 'entities' => []];
        }

        $extracted = $this->extractEntitiesFromText($chunk->title."\n".$chunk->content);
        if (empty($extracted)) {
            return ['entities_count' => 0, 'relations_count' => 0, 'entities' => []];
        }

        $entityModels = [];
        $entityNames = [];

        foreach ($extracted as $item) {
            $entity = MemoryEntity::firstOrCreate(
                [
                    'collection_id' => $collection->id,
                    'normalized_name' => $item['normalized'],
                ],
                [
                    'chunk_id' => $chunk->id,
                    'name' => $item['name'],
                    'entity_type' => $item['type'],
                    'metadata' => ['first_seen_chunk' => $chunk->id],
                ]
            );

            $entityModels[] = $entity;
            $entityNames[] = $entity->name;
        }

        // Tworzenie relacji współwystępowania i zależności pomiędzy wyekstrahowanymi encjami w tym samym fragmencie
        $relationsCount = 0;
        $count = count($entityModels);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < min($count, $i + 5); $j++) {
                $source = $entityModels[$i];
                $target = $entityModels[$j];

                if ($source->id === $target->id) {
                    continue;
                }

                $relation = MemoryRelation::firstOrNew([
                    'collection_id' => $collection->id,
                    'source_entity_id' => $source->id,
                    'target_entity_id' => $target->id,
                ]);

                $relation->relation_type = $this->inferRelationType($chunk->content, $source->name, $target->name);
                $relation->weight = ($relation->exists ? $relation->weight + 0.5 : 1.0);
                $relation->chunk_id = $chunk->id;
                $relation->save();

                $relationsCount++;
            }
        }

        return [
            'entities_count' => count($entityModels),
            'relations_count' => $relationsCount,
            'entities' => $entityNames,
        ];
    }

    public function search(MemoryCollection $collection, string $query, int $limit = 5, string $mode = 'hybrid'): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $candidateMap = [];

        // 1. Wyszukiwanie gęste (Dense Vector Search)
        if (in_array($mode, ['hybrid', 'dense'], true)) {
            $store = $this->getStore($collection);
            $queryEmbedding = $this->llmGateway->embed(new EmbeddingRequest(
                input: $query,
                model: $collection->embedding_model,
                dimensions: $collection->dimensions,
            ));

            $vector = is_array($queryEmbedding->embeddings[0] ?? null)
                ? $queryEmbedding->embeddings[0]
                : $queryEmbedding->embeddings;

            $denseResults = $store->search($collection, $vector, max($limit * 2, 10));

            foreach ($denseResults as $res) {
                $chunkId = (string) ($res['id'] ?? '');
                $chunk = $chunkId !== '' ? MemoryChunk::find($chunkId) : null;
                $key = $chunk ? (string) $chunk->id : md5(json_encode($res['payload'] ?? []));

                $candidateMap[$key] = [
                    'chunk' => $chunk,
                    'dense_score' => (float) ($res['score'] ?? 0.0),
                    'bm25_score' => 0.0,
                    'graph_score' => 0.0,
                    'matched_entities' => [],
                    'payload' => $res['payload'] ?? [
                        'chunk_id' => $chunk?->id,
                        'title' => $chunk?->title ?? '',
                        'content' => $chunk?->content ?? '',
                    ],
                ];
            }
        }

        // 2. Wyszukiwanie rzadkie / pełnotekstowe BM25 po wszystkich fragmentach kolekcji
        if (in_array($mode, ['hybrid', 'bm25'], true)) {
            $allChunks = $collection->chunks()->take(250)->get();

            foreach ($allChunks as $chunk) {
                $bm25 = $this->computeBm25Score($query, (string) $chunk->title, (string) $chunk->content);
                if ($bm25 <= 0.0 && ! isset($candidateMap[$chunk->id])) {
                    continue;
                }

                if (! isset($candidateMap[$chunk->id])) {
                    $candidateMap[$chunk->id] = [
                        'chunk' => $chunk,
                        'dense_score' => 0.0,
                        'bm25_score' => $bm25,
                        'graph_score' => 0.0,
                        'matched_entities' => [],
                        'payload' => [
                            'chunk_id' => $chunk->id,
                            'collection_id' => $collection->id,
                            'title' => $chunk->title,
                            'content' => mb_substr((string) $chunk->content, 0, 300),
                        ],
                    ];
                } else {
                    $candidateMap[$chunk->id]['bm25_score'] = $bm25;
                }
            }
        }

        // 3. Ekspansja Grafu Wiedzy (GraphRAG 1-hop)
        if ($mode === 'hybrid' && Schema::hasTable('memory_entities') && Schema::hasTable('memory_relations')) {
            $this->applyGraphExpansion($collection, $query, $candidateMap);
        }

        if (empty($candidateMap)) {
            return [];
        }

        // 4. Tylko dense lub tylko bm25 -> bez pełnego fuzjowania hybrydowego
        if ($mode === 'dense') {
            $list = array_values($candidateMap);
            foreach ($list as &$item) {
                $item['score'] = round((float) $item['dense_score'], 4);
            }
            unset($item);
            usort($list, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_slice($list, 0, $limit);
        }

        if ($mode === 'bm25') {
            $list = array_values($candidateMap);
            foreach ($list as &$item) {
                $item['score'] = round((float) $item['bm25_score'], 4);
            }
            unset($item);
            usort($list, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_slice($list, 0, $limit);
        }

        // 5. Cross-Encoder Reranker dla trybu hybrydowego
        return $this->rerank($query, array_values($candidateMap), $limit);
    }

    public function computeBm25Score(string $query, string $title, string $content): float
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return 0.0;
        }

        $titleLower = mb_strtolower($title);
        $contentLower = mb_strtolower($content);
        $docTokens = $this->tokenize($title.' '.$content);

        if (empty($docTokens)) {
            return 0.0;
        }

        $docLen = count($docTokens);
        $avgDocLen = 60.0;
        $k1 = 1.5;
        $b = 0.75;

        $freqMap = array_count_values($docTokens);
        $rawScore = 0.0;
        $matchedTerms = 0;

        foreach ($queryTokens as $qToken) {
            $tf = (float) ($freqMap[$qToken] ?? 0);

            // Dopasowanie prefiksowe / rdzeniowe lub dokładny podciąg (np. kody błędów, identyfikatory)
            if ($tf === 0.0 && mb_strlen($qToken) >= 4) {
                $stem = mb_substr($qToken, 0, max(4, mb_strlen($qToken) - 1));
                foreach ($freqMap as $docWord => $count) {
                    if (str_starts_with((string) $docWord, $stem)) {
                        $tf += $count * 0.8;
                    }
                }
            }

            if ($tf > 0.0) {
                $matchedTerms++;
                $titleBonus = str_contains($titleLower, $qToken) ? 1.6 : 1.0;
                $numerator = $tf * ($k1 + 1.0);
                $denominator = $tf + $k1 * (1.0 - $b + $b * ($docLen / $avgDocLen));
                $rawScore += ($numerator / $denominator) * $titleBonus;
            }
        }

        if ($matchedTerms === 0) {
            return 0.0;
        }

        $coverageRatio = $matchedTerms / count($queryTokens);
        $exactPhraseBonus = str_contains($contentLower, mb_strtolower(trim($query))) ? 0.25 : 0.0;

        // Normalizacja wyniku BM25 do przedziału [0.0, 1.0]
        $normalized = ($rawScore / (count($queryTokens) * 2.2)) * (0.5 + 0.5 * $coverageRatio) + $exactPhraseBonus;

        return round(max(0.0, min(1.0, $normalized)), 4);
    }

    public function rerank(string $query, array $candidates, int $limit = 5): array
    {
        $queryLower = mb_strtolower(trim($query));

        foreach ($candidates as &$candidate) {
            /** @var ?MemoryChunk $chunk */
            $chunk = $candidate['chunk'] ?? null;
            $title = (string) ($chunk?->title ?? ($candidate['payload']['title'] ?? ''));
            $content = (string) ($chunk?->content ?? ($candidate['payload']['content'] ?? ''));

            if ((float) ($candidate['bm25_score'] ?? 0.0) === 0.0 && ($title !== '' || $content !== '')) {
                $candidate['bm25_score'] = $this->computeBm25Score($query, $title, $content);
            }

            $dense = (float) ($candidate['dense_score'] ?? 0.0);
            $bm25 = (float) ($candidate['bm25_score'] ?? 0.0);
            $graph = (float) ($candidate['graph_score'] ?? 0.0);

            // Cross-Encoder Feature Fusion:
            // - jeżeli oba sygnały (wektorowy i leksykalny BM25) są obecne, dostają bonus synergii,
            // - jeżeli dokument zawiera dokładny identyfikator/frazę z zapytania, wynik rośnie nawet przy słabszym wektorze.
            $synergyBonus = ($dense > 0.4 && $bm25 > 0.3) ? 0.10 : 0.0;
            $exactMatchBonus = ($queryLower !== '' && (str_contains(mb_strtolower($title), $queryLower) || str_contains(mb_strtolower($content), $queryLower)))
                ? 0.15
                : 0.0;

            $fusedScore = ($dense * 0.45) + ($bm25 * 0.40) + ($graph * 0.15) + $synergyBonus + $exactMatchBonus;

            // Jeżeli jeden ze składników (np. dokładny kod błędu w BM25 lub wysoki wektor) jest bardzo silny, nie tłumimy go
            $maxSingleSignal = max($dense * 0.92, $bm25 * 0.92);
            $finalScore = max($fusedScore, $maxSingleSignal + ($graph * 0.10));

            $candidate['score'] = round(max(0.0, min(1.0, $finalScore)), 4);
            $candidate['dense_score'] = round($dense, 4);
            $candidate['bm25_score'] = round($bm25, 4);
            $candidate['graph_score'] = round($graph, 4);
        }
        unset($candidate);

        usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Wyszukuje encje z zapytania i rozszerza kandydatów o sąsiednie węzły grafu (1-hop).
     *
     * @param  array<string, array<string, mixed>>  $candidateMap
     */
    private function applyGraphExpansion(MemoryCollection $collection, string $query, array &$candidateMap): void
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return;
        }

        $matchedEntities = MemoryEntity::query()
            ->where('collection_id', $collection->id)
            ->where(function ($q) use ($queryTokens): void {
                foreach ($queryTokens as $token) {
                    if (mb_strlen($token) >= 3) {
                        $q->orWhere('normalized_name', 'like', "%{$token}%");
                    }
                }
            })
            ->limit(25)
            ->get();

        if ($matchedEntities->isEmpty()) {
            return;
        }

        $directEntityIds = $matchedEntities->pluck('id')->all();

        // Pobieramy relacje wychodzące i przychodzące (1-hop w Grafie Wiedzy)
        $relations = MemoryRelation::query()
            ->where('collection_id', $collection->id)
            ->where(function ($q) use ($directEntityIds): void {
                $q->whereIn('source_entity_id', $directEntityIds)
                    ->orWhereIn('target_entity_id', $directEntityIds);
            })
            ->with(['sourceEntity', 'targetEntity'])
            ->get();

        $chunkBoosts = [];
        $chunkEntityNames = [];

        foreach ($matchedEntities as $entity) {
            if ($entity->chunk_id) {
                $chunkBoosts[$entity->chunk_id] = ($chunkBoosts[$entity->chunk_id] ?? 0.0) + 0.60;
                $chunkEntityNames[$entity->chunk_id][] = $entity->name;
            }
        }

        foreach ($relations as $rel) {
            $hopChunkIds = array_filter([
                $rel->chunk_id,
                $rel->sourceEntity?->chunk_id,
                $rel->targetEntity?->chunk_id,
            ]);

            foreach ($hopChunkIds as $cId) {
                $chunkBoosts[$cId] = ($chunkBoosts[$cId] ?? 0.0) + min(0.45, 0.25 * $rel->weight);
                if ($rel->sourceEntity) {
                    $chunkEntityNames[$cId][] = $rel->sourceEntity->name;
                }
                if ($rel->targetEntity) {
                    $chunkEntityNames[$cId][] = $rel->targetEntity->name;
                }
            }
        }

        foreach ($chunkBoosts as $chunkId => $boost) {
            $graphScore = min(1.0, $boost);
            $entitiesList = array_values(array_unique($chunkEntityNames[$chunkId] ?? []));

            if (! isset($candidateMap[$chunkId])) {
                $chunk = MemoryChunk::find($chunkId);
                if (! $chunk) {
                    continue;
                }

                $candidateMap[$chunkId] = [
                    'chunk' => $chunk,
                    'dense_score' => 0.0,
                    'bm25_score' => $this->computeBm25Score($query, (string) $chunk->title, (string) $chunk->content),
                    'graph_score' => $graphScore,
                    'matched_entities' => $entitiesList,
                    'payload' => [
                        'chunk_id' => $chunk->id,
                        'collection_id' => $collection->id,
                        'title' => $chunk->title,
                        'content' => mb_substr((string) $chunk->content, 0, 300),
                    ],
                ];
            } else {
                $candidateMap[$chunkId]['graph_score'] = max((float) $candidateMap[$chunkId]['graph_score'], $graphScore);
                $candidateMap[$chunkId]['matched_entities'] = array_values(array_unique(array_merge(
                    $candidateMap[$chunkId]['matched_entities'] ?? [],
                    $entitiesList
                )));
            }
        }
    }

    /**
     * Ekstrahuje encje techniczne, identyfikatory, nazwy własne oraz wyróżnione pojęcia Markdown.
     *
     * @return list<array{name: string, normalized: string, type: string}>
     */
    private function extractEntitiesFromText(string $text): array
    {
        $entities = [];
        $seen = [];

        // 1. Pojęcia w backtickach `...` oraz pogrubione **...** (np. nazwy usług, klas, komend, kodów)
        if (preg_match_all('/`([A-Za-z0-9_\-\.:\/]{3,50})`|\*\*([A-Za-z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ_\-\s]{3,45})\*\*/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $raw = trim($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
                $this->addExtractedEntity($entities, $seen, $raw, $m[1] !== '' ? 'identifier' : 'concept');
            }
        }

        // 2. Identyfikatory techniczne, kody błędów (np. ERR-409, Qdrant, Docker, PostgreSQL, OpenClaw, CamelCase)
        if (preg_match_all('/\b(?:[A-Z]{2,6}[\-_]\d{2,6}|[A-Z][a-z0-9]+[A-Z][A-Za-z0-9]+|[A-Z][A-Za-z0-9_\-]{2,30})\b/u', $text, $capMatches)) {
            foreach ($capMatches[0] as $word) {
                $lower = mb_strtolower($word);
                if (in_array($lower, self::STOP_WORDS, true) || mb_strlen($word) < 3) {
                    continue;
                }
                $type = preg_match('/^[A-Z]{2,6}[\-_]\d+$/', $word) === 1 ? 'identifier' : 'technology';
                $this->addExtractedEntity($entities, $seen, $word, $type);
            }
        }

        return array_slice($entities, 0, 20);
    }

    /**
     * @param  list<array{name: string, normalized: string, type: string}>  $entities
     * @param  array<string, bool>  $seen
     */
    private function addExtractedEntity(array &$entities, array &$seen, string $name, string $type): void
    {
        $clean = trim($name, " \t\n\r\0\x0B.,;:!?()[]\"'");
        $normalized = Str::lower($clean);

        if ($normalized === '' || mb_strlen($normalized) < 3 || in_array($normalized, self::STOP_WORDS, true)) {
            return;
        }

        if (isset($seen[$normalized])) {
            return;
        }

        $seen[$normalized] = true;
        $entities[] = [
            'name' => $clean,
            'normalized' => $normalized,
            'type' => $type,
        ];
    }

    private function inferRelationType(string $content, string $sourceName, string $targetName): string
    {
        $lower = mb_strtolower($content);
        if (str_contains($lower, 'wymaga') || str_contains($lower, 'zależy') || str_contains($lower, 'depends')) {
            return 'depends_on';
        }
        if (str_contains($lower, 'używa') || str_contains($lower, 'korzysta') || str_contains($lower, 'uses')) {
            return 'uses';
        }

        return 'co_occurs_with';
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $lower = mb_strtolower($text);
        $cleaned = preg_replace('/[^\p{L}\p{N}_\-]+/u', ' ', $lower) ?? $lower;
        $parts = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) >= 2 && ! in_array($part, self::STOP_WORDS, true)) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }
}

