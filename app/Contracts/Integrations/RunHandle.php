<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

class RunHandle
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $status, // 'started', 'running', 'completed', 'failed'
        public ?string $output = null,
        public array $metadata = []
    ) {}
}
