<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

class ConnectionTestResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public bool $success,
        public int $latencyMs,
        public string $message,
        public array $details = [],
    ) {}
}
