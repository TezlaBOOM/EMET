<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

class EmbeddingResponse
{
    /**
     * @param list<float>|list<list<float>> $embeddings
     */
    public function __construct(
        public array $embeddings,
        public string $model,
        public int $totalTokens,
        public int $latencyMs,
        public ?int $accountId = null,
    ) {}
}
