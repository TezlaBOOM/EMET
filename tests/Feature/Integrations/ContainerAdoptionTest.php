<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\AutoConfigProfile;
use App\Models\IntegrationContainer;
use App\Models\User;
use App\Services\Integrations\ContainerAdoptionService;
use App\Services\Integrations\DockerSocketService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContainerAdoptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'integrations.manage', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@agenthub.test',
        ]);
        $this->adminUser->givePermissionTo('integrations.manage');

        // Przygotowanie mockowych kontenerów
        DockerSocketService::setMockContainers([
            [
                'Id' => 'cont-ollama-123456',
                'Names' => ['/ollama-local'],
                'Image' => 'ollama/ollama:latest',
                'State' => 'running',
                'Ports' => [['PublicPort' => 11434]],
                'Labels' => ['agenthub.runtime=ollama'],
            ],
            [
                'Id' => 'cont-hermes-654321',
                'Names' => ['/hermes-agent-prod'],
                'Image' => 'nousresearch/hermes-agent:v1.2',
                'State' => 'running',
                'Ports' => [['PublicPort' => 8080]],
                'Labels' => [],
            ],
            [
                'Id' => 'cont-postgres-999999',
                'Names' => ['/db-postgres'],
                'Image' => 'postgres:16',
                'State' => 'running',
                'Ports' => [['PublicPort' => 5432]],
                'Labels' => [],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DockerSocketService::setMockContainers(null);
        parent::tearDown();
    }

    public function test_it_discovers_containers_and_matches_adapter_signatures(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $containers = $service->detectContainers();

        $this->assertCount(3, $containers);

        $ollama = IntegrationContainer::where('container_id', 'cont-ollama-123456')->first();
        $this->assertNotNull($ollama);
        $this->assertEquals('ollama', $ollama->detected_type);
        $this->assertEquals('none', $ollama->adoption_mode);

        $hermes = IntegrationContainer::where('container_id', 'cont-hermes-654321')->first();
        $this->assertNotNull($hermes);
        $this->assertEquals('hermes', $hermes->detected_type);

        $postgres = IntegrationContainer::where('container_id', 'cont-postgres-999999')->first();
        $this->assertNotNull($postgres);
        $this->assertEquals('unknown', $postgres->detected_type);
    }

    public function test_it_adopts_containers_in_observe_and_managed_modes(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $service->detectContainers();

        $container = IntegrationContainer::where('container_id', 'cont-ollama-123456')->firstOrFail();

        // 1. Adopcja observe
        $service->adoptContainer($container, 'observe');
        $this->assertEquals('observe', $container->fresh()->adoption_mode);

        // 2. Adopcja managed z dołączeniem do sieci
        $service->adoptContainer($container, 'managed', true);
        $this->assertEquals('managed', $container->fresh()->adoption_mode);
        $this->assertEquals('agenthub-net', $container->fresh()->network);
    }

    public function test_it_protects_unadopted_and_observe_containers_from_modification(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $service->detectContainers();

        $unadopted = IntegrationContainer::where('container_id', 'cont-postgres-999999')->firstOrFail();
        $this->assertEquals('none', $unadopted->adoption_mode);

        // Próba konfiguracji niezaadoptowanego kontenera musi rzucić wyjątek
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/nie został zaadoptowany/');
        $service->configureContainer($unadopted, ['some' => 'config']);
    }

    public function test_it_enforces_exec_allowlist_security(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $service->detectContainers();

        $container = IntegrationContainer::where('container_id', 'cont-ollama-123456')->firstOrFail();
        $service->adoptContainer($container, 'managed');

        // Dozwolone polecenie z execAllowlist
        $res = $service->executeAllowlistedCommand($container, 'ollama list');
        $this->assertStringContainsString('ollama list', $res['output']);

        // Niedozwolone polecenie (np. 'cat /etc/shadow' lub 'rm -rf /') musi zostać natychmiast zablokowane
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Odmowa wykonania: polecenie/');
        $service->executeAllowlistedCommand($container, 'cat /etc/shadow');
    }

    public function test_it_executes_autoconfig_profile_successfully(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $service->detectContainers();

        $container = IntegrationContainer::where('container_id', 'cont-ollama-123456')->firstOrFail();
        $service->adoptContainer($container, 'managed');

        $profile = AutoConfigProfile::create([
            'name' => 'Ollama Auto Llama3',
            'slug' => 'ollama-auto-llama3',
            'adapter_type' => 'ollama',
            'target_model' => 'auto',
            'steps' => [
                ['name' => 'Pull model', 'action' => 'pull'],
                ['name' => 'Configure endpoint', 'action' => 'endpoint'],
            ],
            'is_active' => true,
        ]);

        $run = $service->runAutoConfig($container, $profile);

        $this->assertEquals('completed', $run->status);
        $this->assertNotNull($container->fresh()->config);
        $this->assertEquals('llama3.2', $container->fresh()->config['model']);
        $this->assertEquals('ollama-auto-llama3', $container->fresh()->config['profile_slug']);
    }

    public function test_it_rolls_back_automatically_on_autoconfig_health_check_failure(): void
    {
        /** @var ContainerAdoptionService $service */
        $service = app(ContainerAdoptionService::class);
        $service->detectContainers();

        $container = IntegrationContainer::where('container_id', 'cont-ollama-123456')->firstOrFail();
        $container->config = ['initial_setting' => 'stable_v1'];
        $container->save();

        $service->adoptContainer($container, 'managed');

        $failProfile = AutoConfigProfile::create([
            'name' => 'Ollama Failing Profile',
            'slug' => 'ollama-fail-probe',
            'adapter_type' => 'ollama',
            'target_model' => 'llama3.2',
            'steps' => [
                'fail_health_probe' => true,
            ],
            'is_active' => true,
        ]);

        $run = $service->runAutoConfig($container, $failProfile);

        // Weryfikacja rollbacku
        $this->assertEquals('rolled_back', $run->status);
        $this->assertStringContainsString('Błąd health-check', $run->error_message);

        // Konfiguracja kontenera musi zostać przywrócona do snapshotu początkowego
        $this->assertEquals(['initial_setting' => 'stable_v1'], $container->fresh()->config);
    }

    public function test_it_renders_containers_page_and_allows_detect_via_http(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('integrations.containers.index'));
        $response->assertStatus(200);
        $response->assertSee('Wykryj kontenery');

        $detectRes = $this->actingAs($this->adminUser)->post(route('integrations.containers.detect'));
        $detectRes->assertRedirect();
        $detectRes->assertSessionHas('success');

        $this->assertDatabaseHas('integration_containers', [
            'container_id' => 'cont-ollama-123456',
        ]);
    }
}
