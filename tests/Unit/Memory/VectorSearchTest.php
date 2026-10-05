<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Contracts\Llm\LlmGatewayInterface;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\MemoryCollection;
use App\Services\MemoryService\EmbeddingService;
use App\Services\MemoryService\PgVectorStore;
use App\Services\MemoryService\QdrantVectorStore;
use App\Services\MemoryService\TextChunker;
use Database\Seeders\ModelPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VectorSearchTest extends TestCase
{
    use RefreshDatabase;

    protected EmbeddingService $embeddingService;
    protected LlmProvider $provider;
    protected MemoryCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModelPricingSeeder::class);

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI Test',
            'slug' => 'openai',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'is_active' => true,
        ]);

        LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Mock Key for Embeddings',
            'api_key' => 'mock-embedding-key-123',
            'weight' => 10,
            'current_status' => 'active',
        ]);

        $this->collection = MemoryCollection::create([
            'name' => 'Kolekcja Wiedzy',
            'slug' => 'kolekcja-wiedzy',
            'vector_store' => 'pgvector',
            'embedding_provider_id' => $this->provider->id,
            'embedding_model' => 'text-embedding-3-small',
            'dimensions' => 128,
            'distance_metric' => 'cosine',
            'is_default' => true,
        ]);

        $this->embeddingService = app(EmbeddingService::class);
    }

    public function test_text_chunker_splits_markdown_properly(): void
    {
        $chunker = new TextChunker();

        $markdown = <<<MD
# Wprowadzenie do Architektury
To jest pierwszy akapit dokumentu wprowadzający do architektury platformy.

## Komponenty Systemu
AgentHub składa się z 7 bloków wizualnych oraz dedykowanego podsystemu bramy LLM Gateway.
Każdy moduł jest niezależny i rejestrowany przez manifest module.json.

## Pamięć Wektorowa
Pamięć opiera się o Qdrant lub pgvector, zapewniając semantyczne wyszukiwanie wiedzy.
MD;

        $chunks = $chunker->chunk($markdown, 150, 30);

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThanOrEqual(2, count($chunks));
        $this->assertArrayHasKey('title', $chunks[0]);
        $this->assertArrayHasKey('content', $chunks[0]);
    }

    public function test_vector_store_upsert_and_cosine_similarity_search(): void
    {
        $store = new PgVectorStore();
        $store->createCollection($this->collection);

        // Zapis 2 wektorów o znanej geometrii
        $vectorA = array_fill(0, 128, 0.0);
        $vectorA[0] = 1.0; // Kierunek w osi X

        $vectorB = array_fill(0, 128, 0.0);
        $vectorB[1] = 1.0; // Kierunek w osi Y (ortogonalny do X)

        $store->upsertPoint($this->collection, 'point-x', $vectorA, ['title' => 'Oś X']);
        $store->upsertPoint($this->collection, 'point-y', $vectorB, ['title' => 'Oś Y']);

        // Zapytanie z wektorem bliskim osi X
        $queryVector = array_fill(0, 128, 0.0);
        $queryVector[0] = 0.95;
        $queryVector[1] = 0.05;

        $results = $store->search($this->collection, $queryVector, 2);

        $this->assertCount(2, $results);
        // Pierwszy wynik musi być point-x z najwyższym podobieństwem kosinusowym
        $this->assertEquals('point-x', $results[0]['id']);
        $this->assertGreaterThan(0.9, $results[0]['score']);
    }

    public function test_embedding_service_ingest_and_search(): void
    {
        $content = <<<MD
# Standard Bezpieczeństwa
Wszystkie klucze API w platformie AgentHub są szyfrowane przy użyciu algorytmu AES-256-CBC.
Nigdy nie przechowujemy kluczy w postaci jawnej (plaintext).

# Zarządzanie Rolami
Role w systemie są oparte o Spatie Permission i obejmują role admin, operator oraz viewer.
MD;

        $chunks = $this->embeddingService->ingestDocument(
            collection: $this->collection,
            title: 'Polityka Bezpieczeństwa',
            content: $content
        );

        $this->assertNotEmpty($chunks);
        $this->assertDatabaseHas('memory_chunks', [
            'collection_id' => $this->collection->id,
            'title' => 'Standard Bezpieczeństwa',
        ]);

        // Wyszukiwanie semantyczne
        $searchResults = $this->embeddingService->search(
            collection: $this->collection,
            query: 'Jak szyfrowane są klucze API?',
            limit: 3
        );

        $this->assertNotEmpty($searchResults);
        $this->assertNotNull($searchResults[0]['chunk']);

        // Test danych grafu powiązań Obsidian
        $graphData = $this->embeddingService->getGraphData($this->collection);
        $this->assertArrayHasKey('nodes', $graphData);
        $this->assertArrayHasKey('links', $graphData);
        $this->assertGreaterThanOrEqual(1, count($graphData['nodes']));
    }

    public function test_qdrant_vector_store_instantiation(): void
    {
        $qdrant = new QdrantVectorStore('http://127.0.0.1:6333');
        $this->assertInstanceOf(QdrantVectorStore::class, $qdrant);
        $this->assertFalse($qdrant->ping()); // offline w testach lokalnych
    }
}
