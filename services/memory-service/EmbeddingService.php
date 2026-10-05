<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Llm\EmbeddingRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryChunk;
use App\Models\MemoryCollection;
use Illuminate\Support\Str;

class EmbeddingService
{
    public function __construct(
        protected LlmGatewayInterface $llmGateway,
        protected TextChunker $chunker,
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

    /**
     * Dzieli dokument i zapisuje jego fragmenty w bazie i indeksie wektorowym
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

            // Zapis do bazy relacyjnej
            $chunk = MemoryChunk::create([
                'id' => $chunkId,
                'collection_id' => $collection->id,
                'title' => $chunkData['title'] ?: $title,
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

            $savedChunks[] = $chunk;
        }

        return $savedChunks;
    }

    /**
     * Semantyczne wyszukiwanie najbardziej relewantnych fragmentów wiedzy
     *
     * @return array<array{chunk: ?MemoryChunk, score: float, payload: array}>
     */
    public function search(MemoryCollection $collection, string $query, int $limit = 5): array
    {
        $store = $this->getStore($collection);

        // Generowanie embeddingu dla zapytania użytkownika
        $queryEmbedding = $this->llmGateway->embed(new EmbeddingRequest(
            input: $query,
            model: $collection->embedding_model,
            dimensions: $collection->dimensions
        ));

        $vector = is_array($queryEmbedding->embeddings[0] ?? null)
            ? $queryEmbedding->embeddings[0]
            : $queryEmbedding->embeddings;

        $results = $store->search($collection, $vector, $limit);
        $output = [];

        foreach ($results as $res) {
            $chunk = MemoryChunk::find($res['id']);
            $output[] = [
                'chunk' => $chunk,
                'score' => $res['score'],
                'payload' => $res['payload'],
            ];
        }

        return $output;
    }

    /**
     * Generuje węzły i krawędzie dla wizualizacji grafowej Obsidian
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
            'id' => 'col_' . $collection->id,
            'name' => $collection->name,
            'group' => 'collection',
            'val' => 20,
        ];

        foreach ($chunks as $chunk) {
            $nodeId = 'chunk_' . $chunk->id;
            $nodes[] = [
                'id' => $nodeId,
                'name' => Str::limit($chunk->title, 25),
                'full_title' => $chunk->title,
                'group' => 'chunk',
                'val' => 8,
            ];

            // Krawędź do kolekcji
            $links[] = [
                'source' => 'col_' . $collection->id,
                'target' => $nodeId,
            ];
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
        ];
    }
}
