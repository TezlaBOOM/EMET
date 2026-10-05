<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

class CompletionRequest
{
    /**
     * @param array<array{role: string, content: string}> $messages
     * @param list<string> $stop
     * @param array<mixed> $tools
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public float $temperature = 0.7,
        public int $maxTokens = 2048,
        public array $stop = [],
        public array $tools = [],
        public ?string $streamChannel = null,
        public array $metadata = [],
    ) {}
}
