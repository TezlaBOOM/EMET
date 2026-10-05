<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

class EmbeddingRequest
{
    /**
     * @param string|list<string> $input
     */
    public function __construct(
        public string|array $input,
        public ?string $model = null,
        public int $dimensions = 1536,
    ) {}
}
