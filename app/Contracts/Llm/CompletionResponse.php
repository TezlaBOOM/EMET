<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

class CompletionResponse
{
    /**
     * @param array<mixed> $toolCalls
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public int $latencyMs,
        public ?int $ttftMs = null,
        public ?int $accountId = null,
        public ?float $estimatedCost = null,
        public array $toolCalls = [],
        public array $raw = [],
    ) {}
}
