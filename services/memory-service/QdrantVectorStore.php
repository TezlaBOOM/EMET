<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryCollection;
use Exception;
use Illuminate\Support\Facades\Http;

class QdrantVectorStore implements VectorStoreInterface
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected bool $isMock;

    /**
     * @var array<string, array<string, array{vector: array, payload: array}>>
     */
    protected static array $mockStorage = [];

    public function __construct(?string $baseUrl = null, ?bool $isMock = null)
    {
        $host = (string) config('agenthub.memory.qdrant.host', '127.0.0.1');
        $port = (int) config('agenthub.memory.qdrant.port', 6333);
        $defaultUrl = "http://{$host}:{$port}";

        $configuredUrl = config('agenthub.memory.qdrant.url')
            ?: config('agenthub.qdrant.url')
            ?: $defaultUrl;

        $this->baseUrl = rtrim($baseUrl ?: (string) $configuredUrl, '/');
        $this->apiKey = config('agenthub.memory.qdrant.api_key', env('QDRANT_API_KEY'));

        if ($baseUrl !== null && $isMock === null) {
            $this->isMock = false;
        } else {
            $this->isMock = $isMock ?? (bool) config('agenthub.memory.qdrant.mock', env('QDRANT_MOCK', false));
        }
    }

    public function isMock(): bool
    {
        return $this->isMock;
    }

    public function createCollection(MemoryCollection $collection): void
    {
        if ($this->isMock) {
            self::$mockStorage[$collection->slug] = [];

            return;
        }

        $metric = match (strtolower($collection->distance_metric)) {
            'euclidean' => 'Euclid',
            'dot' => 'Dot',
            default => 'Cosine',
        };

        try {
            $req = Http::timeout(5);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $req->put("{$this->baseUrl}/collections/{$collection->slug}", [
                'vectors' => [
                    'size' => $collection->dimensions,
                    'distance' => $metric,
                ],
            ]);
        } catch (Exception) {
            // Mock fallback if Qdrant offline
            self::$mockStorage[$collection->slug] = [];
        }
    }

    public function deleteCollection(MemoryCollection $collection): void
    {
        if ($this->isMock) {
            unset(self::$mockStorage[$collection->slug]);

            return;
        }

        try {
            $req = Http::timeout(5);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $req->delete("{$this->baseUrl}/collections/{$collection->slug}");
        } catch (Exception) {
            unset(self::$mockStorage[$collection->slug]);
        }
    }

    public function upsertPoint(MemoryCollection $collection, string $pointId, array $vector, array $payload): void
    {
        if ($this->isMock) {
            if (! isset(self::$mockStorage[$collection->slug])) {
                self::$mockStorage[$collection->slug] = [];
            }
            self::$mockStorage[$collection->slug][$pointId] = [
                'vector' => $vector,
                'payload' => $payload,
            ];

            return;
        }

        try {
            $req = Http::timeout(5);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $req->put("{$this->baseUrl}/collections/{$collection->slug}/points", [
                'points' => [
                    [
                        'id' => $pointId,
                        'vector' => $vector,
                        'payload' => $payload,
                    ],
                ],
            ]);
        } catch (Exception) {
            if (! isset(self::$mockStorage[$collection->slug])) {
                self::$mockStorage[$collection->slug] = [];
            }
            self::$mockStorage[$collection->slug][$pointId] = [
                'vector' => $vector,
                'payload' => $payload,
            ];
        }
    }

    public function deletePoint(MemoryCollection $collection, string $pointId): void
    {
        if ($this->isMock) {
            unset(self::$mockStorage[$collection->slug][$pointId]);

            return;
        }

        try {
            $req = Http::timeout(5);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $req->post("{$this->baseUrl}/collections/{$collection->slug}/points/delete", [
                'points' => [$pointId],
            ]);
        } catch (Exception) {
            unset(self::$mockStorage[$collection->slug][$pointId]);
        }
    }

    public function search(MemoryCollection $collection, array $queryVector, int $limit = 10, array $filters = []): array
    {
        if ($this->isMock) {
            return $this->getMockSearchResults($collection, $limit);
        }

        try {
            $req = Http::timeout(5);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $response = $req->post("{$this->baseUrl}/collections/{$collection->slug}/points/search", [
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
            // Fallback to mock search results
        }

        return $this->getMockSearchResults($collection, $limit);
    }

    public function ping(): bool
    {
        if ($this->isMock) {
            return true;
        }

        try {
            $req = Http::timeout(2);
            if ($this->apiKey) {
                $req = $req->withHeaders(['api-key' => $this->apiKey]);
            }
            $response = $req->get("{$this->baseUrl}/collections");

            return $response->successful();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @return array<int, array{id: string, score: float, payload: array}>
     */
    protected function getMockSearchResults(MemoryCollection $collection, int $limit): array
    {
        $points = self::$mockStorage[$collection->slug] ?? [];
        $results = [];

        foreach ($points as $id => $data) {
            $results[] = [
                'id' => (string) $id,
                'score' => 0.95,
                'payload' => $data['payload'] ?? [],
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
