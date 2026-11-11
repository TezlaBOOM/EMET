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

class EmbeddingService
{
    protected HybridRagInterface $hybridRag;

    public function __construct(
        protected LlmGatewayInterface $llmGateway,
        protected TextChunker $chunker,
        protected QdrantVectorStore $qdrantStore,
        protected PgVectorStore $pgVectorStore,
        ?HybridRagInterface $hybridRag = null,
    ) {
        $this->hybridRag = $hybridRag ?? new HybridRagService($llmGateway, $qdrantStore, $pgVectorStore);
    }

    public function getStore(MemoryCollection $collection): VectorStoreInterface
    {
        return match (strtolower($collection->vector_store)) {
            'pgvector' => $this->pgVectorStore,
            default => $this->qdrantStore,
        };
    }

    public function getHybridRag(): HybridRagInterface
    {
        return $this->hybridRag;
    }

    /**
     * Dzieli dokument, zapisuje jego fragmenty w bazie i indeksie wektorowym oraz ekstrahuje encje i relacje do GraphRAG
     *
     * @return list<MemoryChunk>
     */
    public function ingestDocument(MemoryCollection $collection, string $title, string $content, array $metadata = []): array
    {
        $store = $this->getStore($collection);
        $chunksData = $this->chunker->chunk($content);
        $savedChunks = [];

        foreach ($chunksData as $chunkData) {
            $chunkId = (string) Str::uuid();

            // Generowanie embeddingu dla fragmentu
            $embeddingResponse = $this->llmGateway->embed(new EmbeddingRequest(
                input: $chunkData['content'],
                model: $collection->embedding_model,
                dimensions: $collection->dimensions
            ));

            $vector = is_array($embeddingResponse->embeddings[0] ?? null)
                ? $embeddingResponse->embeddings[0]
                : $embeddingResponse->embeddings;

            $chunkTitle = (! empty($chunkData['title']) && ! str_starts_with($chunkData['title'], 'Fragment '))
                ? $chunkData['title']
                : ($title ?: $chunkData['title']);

            // Zapis do bazy relacyjnej
            $chunk = MemoryChunk::create([
                'id' => $chunkId,
                'collection_id' => $collection->id,
                'title' => $chunkTitle,
                'content' => $chunkData['content'],
                'vector_point_id' => $chunkId,
                'metadata' => array_merge($metadata, [
                    'index' => $chunkData['index'],
                    'model' => $collection->embedding_model,
                ]),
            ]);

            // Zapis wektora do Vector Store
            $store->upsertPoint($collection, $chunkId, $vector, [
                'chunk_id' => $chunkId,
                'collection_id' => $collection->id,
                'title' => $chunk->title,
                'content' => mb_substr($chunk->content, 0, 300),
            ]);

            // Automatyczna ekstrakcja encji i relacji do Grafu Wiedzy (GraphRAG v1.7.0)
            $graphInfo = $this->hybridRag->extractAndStoreGraph($collection, $chunk);
            if (! empty($graphInfo['entities'])) {
                $chunk->update([
                    'metadata' => array_merge($chunk->metadata ?? [], [
                        'entities' => $graphInfo['entities'],
                    ]),
                ]);
            }

            $savedChunks[] = $chunk;
        }

        return $savedChunks;
    }

    /**
     * Hybrydowe lub semantyczne wyszukiwanie najbardziej relewantnych fragmentów wiedzy
     *
     * @return array<array{chunk: ?MemoryChunk, score: float, payload: array}>
     */
    public function search(MemoryCollection $collection, string $query, int $limit = 5, string $mode = 'hybrid'): array
    {
        return $this->hybridRag->search($collection, $query, $limit, $mode);
    }

    /**
     * Generuje węzły i krawędzie dla wizualizacji grafowej Obsidian (kolekcja, fragmenty oraz encje i relacje GraphRAG)
     *
     * @return array{nodes: array, links: array}
     */
    public function getGraphData(MemoryCollection $collection): array
    {
        $chunks = $collection->chunks()->take(100)->get();
        $nodes = [];
        $links = [];

        // Główny węzeł kolekcji
        $nodes[] = [
            'id' => 'col_'.$collection->id,
            'name' => $collection->name,
            'group' => 'collection',
            'val' => 20,
        ];

        foreach ($chunks as $chunk) {
            $nodeId = 'chunk_'.$chunk->id;
            $nodes[] = [
                'id' => $nodeId,
                'name' => Str::limit($chunk->title, 25),
                'full_title' => $chunk->title,
                'group' => 'chunk',
                'val' => 8,
            ];

            // Krawędź do kolekcji
            $links[] = [
                'source' => 'col_'.$collection->id,
                'target' => $nodeId,
            ];
        }

        // Dodanie encji i relacji GraphRAG (v1.7.0)
        if (Schema::hasTable('memory_entities') && Schema::hasTable('memory_relations')) {
            $entities = MemoryEntity::where('collection_id', $collection->id)->take(80)->get();
            foreach ($entities as $entity) {
                $entityNodeId = 'ent_'.$entity->id;
                $nodes[] = [
                    'id' => $entityNodeId,
                    'name' => $entity->name,
                    'full_title' => "{$entity->name} ({$entity->entity_type})",
                    'group' => 'entity',
                    'val' => 6,
                ];

                if ($entity->chunk_id) {
                    $links[] = [
                        'source' => 'chunk_'.$entity->chunk_id,
                        'target' => $entityNodeId,
                    ];
                }
            }

            $relations = MemoryRelation::where('collection_id', $collection->id)->take(120)->get();
            foreach ($relations as $relation) {
                $links[] = [
                    'source' => 'ent_'.$relation->source_entity_id,
                    'target' => 'ent_'.$relation->target_entity_id,
                    'relation' => $relation->relation_type,
                    'weight' => $relation->weight,
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
        ];
    }
}
