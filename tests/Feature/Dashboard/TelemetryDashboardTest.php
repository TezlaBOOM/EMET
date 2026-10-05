<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Events\AgentTelemetryBroadcastEvent;
use App\Events\DashboardMetricsBroadcastEvent;
use App\Models\Agent;
use App\Models\IntegrationInstance;
use App\Models\LlmCall;
use App\Models\LlmProvider;
use App\Models\TelemetryRollup;
use App\Models\User;
use App\Services\TelemetryCollector\TelemetryCollectorService;
use Database\Seeders\ModelPricingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelemetryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected TelemetryCollectorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ModelPricingSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@test.lan',
            'password' => Hash::make('secret123'),
        ]);
        $this->admin->assignRole('admin');

        $this->service = app(TelemetryCollectorService::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/dashboard/tokens')->assertRedirect('/login');
        $this->get('/dashboard/performance')->assertRedirect('/login');
        $this->get('/dashboard/costs')->assertRedirect('/login');
    }

    public function test_admin_can_view_all_dashboard_views(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee(__('dashboard.title'))
            ->assertSee(__('dashboard.live_refresh'));

        $this->actingAs($this->admin)->get('/dashboard/tokens')
            ->assertStatus(200)
            ->assertSee(__('dashboard.sub_tokens'));

        $this->actingAs($this->admin)->get('/dashboard/performance')
            ->assertStatus(200)
            ->assertSee(__('dashboard.sub_performance'));

        $this->actingAs($this->admin)->get('/dashboard/costs')
            ->assertStatus(200)
            ->assertSee(__('dashboard.sub_costs'));
    }

    public function test_metrics_json_api_returns_valid_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/dashboard/api/metrics?period=24h');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
                'active_agents' => ['total_agents', 'active_count', 'agents'],
                'token_metrics' => ['period', 'total_calls', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'by_model', 'by_provider'],
                'performance' => ['total_calls', 'avg_ttft_ms', 'avg_duration_ms', 'p50_duration_ms', 'p95_duration_ms', 'success_rate_percent', 'error_count'],
                'costs' => ['total_cost_usd', 'by_model'],
                'integrations' => ['total_instances', 'running', 'degraded', 'error', 'stopped', 'instances'],
            ]);
    }

    public function test_telemetry_event_recording_and_event_dispatch(): void
    {
        Event::fake([AgentTelemetryBroadcastEvent::class]);

        $agent = Agent::create([
            'name' => 'Data Analyst',
            'slug' => 'data-analyst',
            'runtime' => 'native',
            'pool_id' => null,
            'is_active' => true,
        ]);

        $event = $this->service->recordEvent('tool.called', $agent->id, 'run-test-999', [
            'tool' => 'sql_runner',
            'query' => 'SELECT COUNT(*) FROM users',
        ]);

        $this->assertDatabaseHas('telemetry_events', [
            'id' => $event->id,
            'agent_id' => $agent->id,
            'type' => 'tool.called',
        ]);

        Event::assertDispatched(AgentTelemetryBroadcastEvent::class, function ($e) use ($event) {
            return $e->event->id === $event->id && $e->broadcastOn()[0]->name === 'telemetry.agents';
        });

        // Weryfikacja że agent jest raportowany jako 'working'
        $summary = $this->service->getActiveAgentsSummary();
        $this->assertEquals(1, $summary['active_count']);
        $this->assertEquals('working', $summary['agents'][0]['status']);
    }

    public function test_telemetry_rollups_calculation_and_command(): void
    {
        $provider = LlmProvider::create([
            'name' => 'OpenAI Gateway',
            'slug' => 'openai',
            'driver' => 'openai',
            'is_active' => true,
        ]);

        $oneHourAgo = now()->subMinutes(30);

        // Tworzymy 3 wywołania
        LlmCall::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $provider->id,
            'model' => 'gpt-4o',
            'prompt_tokens' => 1000,
            'completion_tokens' => 200,
            'total_tokens' => 1200,
            'ttft_ms' => 150,
            'duration_ms' => 800,
            'estimated_cost_usd' => 0.005000,
            'status' => 'success',
            'created_at' => $oneHourAgo,
        ]);

        LlmCall::create([
            'id' => (string) Str::uuid(),
            'provider_id' => $provider->id,
            'model' => 'gpt-4o',
            'prompt_tokens' => 2000,
            'completion_tokens' => 400,
            'total_tokens' => 2400,
            'ttft_ms' => 250,
            'duration_ms' => 1200,
            'estimated_cost_usd' => 0.010000,
            'status' => 'success',
            'created_at' => $oneHourAgo,
        ]);

        // Uruchamiamy komendę rollup dla ostatniej godziny
        $exitCode = Artisan::call('agenthub:telemetry-rollup', [
            '--period' => 'hourly',
            '--hours' => 1,
        ]);

        $this->assertEquals(0, $exitCode);

        // Weryfikujemy zapis w telemetry_rollups
        $rollup = TelemetryRollup::where('period_type', 'hourly')
            ->where('model', 'gpt-4o')
            ->first();

        $this->assertNotNull($rollup);
        $this->assertEquals(2, $rollup->total_calls);
        $this->assertEquals(3000, $rollup->total_prompt_tokens);
        $this->assertEquals(600, $rollup->total_completion_tokens);
        $this->assertEquals(3600, $rollup->total_tokens);
        $this->assertEquals(0.015, $rollup->total_cost_usd);
        $this->assertEquals(200, $rollup->avg_ttft_ms);
        $this->assertEquals(1000, $rollup->avg_duration_ms);
    }

    public function test_integrations_health_summary_in_dashboard(): void
    {
        IntegrationInstance::create([
            'name' => 'Hermes Alpha',
            'slug' => 'hermes-alpha',
            'type' => 'hermes',
            'mode' => 'systemd',
            'status' => 'running',
            'port' => 8110,
        ]);

        IntegrationInstance::create([
            'name' => 'OpenClaw Beta',
            'slug' => 'openclaw-beta',
            'type' => 'openclaw',
            'mode' => 'docker',
            'status' => 'error',
            'port' => 8120,
            'last_error' => 'Connection refused',
        ]);

        $summary = $this->service->getIntegrationsHealthSummary();

        $this->assertEquals(2, $summary['total_instances']);
        $this->assertEquals(1, $summary['running']);
        $this->assertEquals(1, $summary['error']);
    }

    public function test_reverb_dashboard_event_broadcast(): void
    {
        $event = new DashboardMetricsBroadcastEvent([
            'active_agents' => 3,
            'total_tokens' => 150000,
        ]);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals('telemetry.dashboard', $channels[0]->name);
        $this->assertEquals('dashboard.updated', $event->broadcastAs());
        $this->assertEquals(150000, $event->broadcastWith()['total_tokens']);
    }
}
