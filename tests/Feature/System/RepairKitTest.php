<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Contracts\Memory\VectorStoreInterface;
use App\Services\Integrations\DockerSocketService;
use App\Services\MemoryService\MockVectorStore;
use App\Services\MemoryService\QdrantVectorStore;
use Tests\TestCase;

class RepairKitTest extends TestCase
{
    public function test_repairkit_script_exists_and_is_executable(): void
    {
        $script = base_path('scripts/repairkit.sh');
        $symlink = base_path('repairkit.sh');

        $this->assertFileExists($script);
        $this->assertFileExists($symlink);
        $this->assertTrue(is_executable($script));
    }

    public function test_repairkit_dry_run_execution(): void
    {
        $script = base_path('scripts/repairkit.sh');
        $output = [];
        $exitCode = 0;

        exec("bash {$script} --dry-run 2>&1", $output, $exitCode);

        $this->assertEquals(0, $exitCode, 'Repairkit output: '.implode("\n", $output));
        $this->assertStringContainsString('AgentHub System RepairKit', implode("\n", $output));
    }

    public function test_docker_socket_service_mock_driver_activation(): void
    {
        $service = new DockerSocketService;

        // Bez mocka i bez gniazda
        DockerSocketService::disableMockDriver();
        config(['integrations.docker_mock' => false]);

        // Aktywacja mock drivera
        DockerSocketService::enableMockDriver();
        $this->assertTrue($service->isMock());
        $this->assertTrue($service->isAvailable());

        $containers = $service->listContainers();
        $this->assertNotEmpty($containers);
        $this->assertEquals('cont-ollama-mock-01', $containers[0]['Id']);

        // Sprzątanie
        DockerSocketService::disableMockDriver();
    }

    public function test_qdrant_vector_store_mock_mode_ping(): void
    {
        $mockStore = new MockVectorStore;
        $this->assertInstanceOf(VectorStoreInterface::class, $mockStore);
        $this->assertTrue($mockStore->ping());

        // Test QdrantVectorStore w trybie mock
        $qdrantMock = new QdrantVectorStore('http://127.0.0.1:6333', isMock: true);
        $this->assertTrue($qdrantMock->isMock());
        $this->assertTrue($qdrantMock->ping());
    }
}
