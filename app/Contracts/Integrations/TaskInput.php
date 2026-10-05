<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

class TaskInput
{
    /**
     * @param array<string, mixed> $context
     * @param list<string> $tools
     */
    public function __construct(
        public string $prompt,
        public array $context = [],
        public array $tools = []
    ) {}
}
