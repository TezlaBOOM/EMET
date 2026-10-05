<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

class ProvisionRequest
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $type, // 'hermes', 'openclaw', 'claude_code', 'codex'
        public string $name,
        public string $mode = 'systemd', // 'systemd' or 'docker'
        public ?int $port = null,
        public array $config = []
    ) {}
}
