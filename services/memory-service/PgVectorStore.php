<?php

declare(strict_types=1);

namespace App\Services\MemoryService;

use App\Contracts\Memory\VectorStoreInterface;
use App\Models\MemoryCollection;
use Illuminate\Support\Facades\Cache;

class PgVectorStore implements VectorStoreInterface
{
    /**
     * Pamięć podręczna wektorów (do testów i baz bez rozszerzenia pgvector)
     *
     * @var array<string, array<string, array{vector: list<float>, payload: array}>>
     */
    protected static array $inMemoryStore = [];

    public function createCollection(MemoryCollection $collection): void
    {
        self::$inMemoryStore[$collection->slug] = [];
    }

    public function deleteCollection(MemoryCollection $collection): void
    {
        unset(self::$inMemoryStore[$collection->slug]);
    }

    public function upsertPoint(MemoryCollection $collection, string $pointId, array $vector, array $payload): void
    {
        if (! isset(self::$inMemoryStore[$collection->slug])) {
            self::$inMemoryStore[$collection->slug] = [];
        }

        self::$inMemoryStore[$collection->slug][$pointId] = [
            'vector' => $vector,
            'payload' => $payload,
        ];
    }

    public function deletePoint(MemoryCollection $collection, string $pointId): void
    {
        unset(self::$inMemoryStore[$collection->slug][$pointId]);
    }

    public function search(MemoryCollection $collection, array $queryVector, int $limit = 10, array $filters = []): array
    {
        $points = self::$inMemoryStore[$collection->slug] ?? [];
        if (empty($points)) {
            return [];
        }

        $scores = [];
        foreach ($points as $pointId => $data) {
            $sim = $this->cosineSimilarity($queryVector, $data['vector']);
            $scores[] = [
                'id' => $pointId,
                'score' => round($sim, 5),
                'payload' => $data['payload'],
            ];
        }

        // Sortowanie malejąco wg podobieństwa kosinusowego
        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scores, 0, $limit);
    }

    public function ping(): bool
    {
        return true;
    }

    /**
     * Oblicza podobieństwo kosinusowe między dwoma wektorami
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($a), count($b));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
