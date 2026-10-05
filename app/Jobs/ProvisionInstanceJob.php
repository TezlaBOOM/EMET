<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IntegrationInstance;
use App\Models\ProvisioningJob;
use App\Services\IntegrationManager\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionInstanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public IntegrationInstance $instance,
        public ProvisioningJob $jobRecord
    ) {}

    public function handle(IntegrationService $integrationService): void
    {
        $integrationService->executeProvision($this->instance, $this->jobRecord);
    }
}
