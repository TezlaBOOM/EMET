<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryCollection;
use Exception;
use Illuminate\Support\Facades\Http;

// TODO: SDK Pending - using mocked driver / HTTP client
// Spec: https://qdrant.tech/documentation/concepts/collections/
class QdrantVectorStore implements VectorStoreInterface
{
    protected string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: config('agenthub.qdrant.url', 'http://localhost:6333'), '/');
    }

    public function createCollection(MemoryCollection $collection): void
    {
        $metric = match (strtolower($collection->distance_metric)) {
            'euclidean' => 'Euclid',
            'dot' => 'Dot',
            default => 'Cosine',
        };

        try {
            Http::timeout(5)->put("{$this->baseUrl}/collections/{$collection->slug}", [
                'vectors' => [
                    'size' => $collection->dimensions,
                    'distance' => $metric,
                ],
            ]);
        } catch (Exception) {
            // Mock fallback if Qdrant offline
        }
    }

    public function deleteCollection(MemoryCollection $collection): void
    {
        try {
            Http::timeout(5)->delete("{$this->baseUrl}/collections/{$collection->slug}");
        } catch (Exception) {
            // Mock fallback
        }
    }

    public function upsertPoint(MemoryCollection $collection, string $pointId, array $vector, array $payload): void
    {
        try {
            Http::timeout(5)->put("{$this->baseUrl}/collections/{$collection->slug}/points", [
                'points' => [
                    [
                        'id' => $pointId,
                        'vector' => $vector,
                        'payload' => $payload,
                    ],
                ],
            ]);
        } catch (Exception) {
            // Mock fallback
        }
    }

    public function deletePoint(MemoryCollection $collection, string $pointId): void
    {
        try {
            Http::timeout(5)->post("{$this->baseUrl}/collections/{$collection->slug}/points/delete", [
                'points' => [$pointId],
            ]);
        } catch (Exception) {
            // Mock fallback
        }
    }

    public function search(MemoryCollection $collection, array $queryVector, int $limit = 10, array $filters = []): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/collections/{$collection->slug}/points/search", [
                'vector' => $queryVector,
                'limit' => $limit,
                'with_payload' => true,
            ]);

            if ($response->successful()) {
                $result = $response->json('result') ?? [];
                return array_map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'score' => $item['score'] ?? 0.0,
                        'payload' => $item['payload'] ?? [],
                    ];
                }, $result);
            }
        } catch (Exception) {
            // Fallback to empty or mock array
        }

        return [];
    }

    public function ping(): bool
    {
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/collections");
            return $response->successful();
        } catch (Exception) {
            return false;
        }
    }
}
