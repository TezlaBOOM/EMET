<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\AuditLog;
use App\Models\IntegrationInstance;
use App\Models\ProvisioningJob;
use App\Models\User;
use App\Services\IntegrationManager\IntegrationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstanceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected IntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@test.lan',
            'password' => Hash::make('secret123'),
        ]);
        $this->admin->assignRole('admin');

        $this->service = app(IntegrationService::class);
    }

    public function test_guest_cannot_access_integrations(): void
    {
        $this->get('/integrations')->assertRedirect('/login');
    }

    public function test_admin_can_view_integrations_page_and_subcategories(): void
    {
        $response = $this->actingAs($this->admin)->get('/integrations');
        $response->assertStatus(200);
        $response->assertSee('Wszystkie');

        $this->actingAs($this->admin)->get('/integrations/hermes')->assertStatus(200);
        $this->actingAs($this->admin)->get('/integrations/openclaw')->assertStatus(200);
        $this->actingAs($this->admin)->get('/integrations/claude')->assertStatus(200);
        $this->actingAs($this->admin)->get('/integrations/codex')->assertStatus(200);
    }

    public function test_provision_instance_success_and_port_allocation(): void
    {
        $response = $this->actingAs($this->admin)->post('/integrations', [
            'name' => 'Hermes Production Worker',
            'type' => 'hermes',
            'mode' => 'systemd',
        ]);

        $response->assertSessionHas('success');

        $instance = IntegrationInstance::where('name', 'Hermes Production Worker')->first();
        $this->assertNotNull($instance);
        $this->assertEquals('running', $instance->status);
        $this->assertGreaterThanOrEqual(8100, $instance->port);
        $this->assertLessThanOrEqual(8900, $instance->port);
        $this->assertEquals("http://127.0.0.1:{$instance->port}", $instance->endpoint_url);

        $job = ProvisioningJob::where('instance_id', $instance->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('completed', $job->status);
        $this->assertStringContainsString('jest aktywna i gotowa', $job->logs ?? '');

        // Drugi provisioning alokuje kolejny port
        $response2 = $this->actingAs($this->admin)->post('/integrations', [
            'name' => 'OpenClaw Dev Crawler',
            'type' => 'openclaw',
            'mode' => 'docker',
        ]);
        $response2->assertSessionHas('success');

        $instance2 = IntegrationInstance::where('name', 'OpenClaw Dev Crawler')->first();
        $this->assertNotNull($instance2);
        $this->assertNotEquals($instance->port, $instance2->port);
    }

    public function test_provision_instance_rollback_on_failure(): void
    {
        // Nazwa z 'fail-probe' wywołuje symulowany błąd i rollback w IntegrationService
        $response = $this->actingAs($this->admin)->post('/integrations', [
            'name' => 'Failing Service fail-probe',
            'type' => 'claude_code',
            'mode' => 'systemd',
        ]);

        $response->assertSessionHas('success'); // Kontroler przyjmuje zgłoszenie i raportuje w sesji

        $instance = IntegrationInstance::where('name', 'Failing Service fail-probe')->first();
        $this->assertNotNull($instance);
        $this->assertEquals('error', $instance->status);
        $this->assertNotNull($instance->last_error);

        $job = ProvisioningJob::where('instance_id', $instance->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('rolled_back', $job->status);
        $this->assertStringContainsString('automatycznego rollbacku', $job->logs);
    }

    public function test_reconciliation_command_and_endpoint(): void
    {
        $instance = IntegrationInstance::create([
            'name' => 'Hermes Check',
            'slug' => 'hermes-check',
            'type' => 'hermes',
            'mode' => 'systemd',
            'port' => 8150,
            'status' => 'unknown',
        ]);

        $exitCode = Artisan::call('agenthub:reconcile-instances');
        $this->assertEquals(0, $exitCode);

        $instance->refresh();
        $this->assertEquals('running', $instance->status);
        $this->assertNotNull($instance->health_checked_at);

        // Test przez endpoint POST /integrations/reconcile
        $response = $this->actingAs($this->admin)->post('/integrations/reconcile');
        $response->assertSessionHas('success');
    }

    public function test_delete_instance(): void
    {
        $instance = IntegrationInstance::create([
            'name' => 'To be deleted',
            'slug' => 'to-be-deleted',
            'type' => 'codex',
            'mode' => 'docker',
            'port' => 8199,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->admin)->delete("/integrations/{$instance->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('integration_instances', ['id' => $instance->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'integration.deleted',
            'entity_id' => (string) $instance->id,
        ]);
    }
}
