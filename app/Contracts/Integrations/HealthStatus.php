<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

class HealthStatus
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public bool $isHealthy,
        public string $status, // 'running', 'stopped', 'degraded', 'unreachable'
        public ?int $latencyMs = null,
        public array $details = []
    ) {}
}
