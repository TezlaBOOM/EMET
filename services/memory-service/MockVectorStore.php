<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryCollection;

class MockVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, array<string, array{vector: array, payload: array}>>
     */
    protected static array $storage = [];

    public function createCollection(MemoryCollection $collection): void
    {
        self::$storage[$collection->slug] = [];
    }

    public function deleteCollection(MemoryCollection $collection): void
    {
        unset(self::$storage[$collection->slug]);
    }

    public function upsertPoint(MemoryCollection $collection, string $pointId, array $vector, array $payload): void
    {
        if (! isset(self::$storage[$collection->slug])) {
            self::$storage[$collection->slug] = [];
        }

        self::$storage[$collection->slug][$pointId] = [
            'vector' => $vector,
            'payload' => $payload,
        ];
    }

    public function deletePoint(MemoryCollection $collection, string $pointId): void
    {
        unset(self::$storage[$collection->slug][$pointId]);
    }

    public function search(MemoryCollection $collection, array $queryVector, int $limit = 10, array $filters = []): array
    {
        $points = self::$storage[$collection->slug] ?? [];
        $results = [];

        foreach ($points as $id => $data) {
            $results[] = [
                'id' => $id,
                'score' => 0.95,
                'payload' => $data['payload'] ?? [],
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function ping(): bool
    {
        return true;
    }

    public static function clear(): void
    {
        self::$storage = [];
    }
}
