<?php

declare(strict_types=1);

namespace App\Contracts\Memory;

use App\Models\MemoryCollection;

interface VectorStoreInterface
{
    /** Utworzenie nowej kolekcji wektorowej o zadanych wymiarach i metryce odległości */
    public function createCollection(MemoryCollection $collection): void;

    /** Usunięcie kolekcji wektorowej */
    public function deleteCollection(MemoryCollection $collection): void;

    /** Zapisanie punktu/wektora z ładunkiem metadanych */
    public function upsertPoint(MemoryCollection $collection, string $pointId, array $vector, array $payload): void;

    /** Usunięcie punktu z kolekcji */
    public function deletePoint(MemoryCollection $collection, string $pointId): void;

    /** Wyszukiwanie wektorowe k-najbliższych sąsiadów z opcjonalnymi filtrami */
    public function search(MemoryCollection $collection, array $queryVector, int $limit = 10, array $filters = []): array;

    /** Sprawdzenie stanu usługi wektorowej */
    public function ping(): bool;
}
