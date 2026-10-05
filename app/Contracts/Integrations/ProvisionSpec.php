<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

class ProvisionSpec
{
    /**
     * @param array<string, string> $environment
     * @param array<string, string> $renderedFiles
     */
    public function __construct(
        public string $serviceName,
        public string $workDir,
        public array $environment = [],
        public array $renderedFiles = [],
        public ?string $startCommand = null
    ) {}
}
