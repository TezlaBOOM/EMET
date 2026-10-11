<?php

declare(strict_types=1);

namespace Tests\Feature\AiSettings;

use App\Contracts\Llm\ModelManagementInterface;
use App\Models\Agent;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user and permissions
        $role = Role::firstOrCreate(['name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'ai_settings.manage']);
        $role->givePermissionTo($permission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->provider = LlmProvider::create([
            'name' => 'Anthropic',
            'slug' => 'anthropic',
            'driver' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'is_active' => true,
            'default_model' => 'claude-3-5-sonnet-20241022',
        ]);
    }

    public function test_admin_can_view_models_index(): void
    {
        LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'claude-3-5-sonnet',
            'display_name' => 'Claude 3.5 Sonnet',
            'context_window' => 200000,
            'capabilities' => ['chat', 'vision'],
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('ai-settings.models.index'));

        $response->assertOk();
        $response->assertSee('Claude 3.5 Sonnet');
        $response->assertSee('claude-3-5-sonnet');
    }

    public function test_admin_can_store_manual_model(): void
    {
        $response = $this->actingAs($this->admin)->post(route('ai-settings.models.store'), [
            'provider_id' => $this->provider->id,
            'model_key' => 'custom-claude-model',
            'display_name' => 'Custom Claude',
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'capabilities' => ['chat', 'tools'],
            'input_cost_per_million' => 3.5,
            'output_cost_per_million' => 15.0,
        ]);

        $response->assertRedirect(route('ai-settings.models.index'));
        $this->assertDatabaseHas('models', [
            'model_key' => 'custom-claude-model',
            'display_name' => 'Custom Claude',
            'is_manual' => true,
        ]);
        $this->assertDatabaseHas('model_pricing', [
            'model_pattern' => 'custom-claude-model',
            'input_cost_per_million' => 3.5,
        ]);
    }

    public function test_updating_model_marks_overrides(): void
    {
        $model = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'claude-test',
            'display_name' => 'Original Name',
            'context_window' => 100000,
            'capabilities' => ['chat'],
            'enabled' => true,
            'overrides' => [],
        ]);

        $response = $this->actingAs($this->admin)->put(route('ai-settings.models.update', $model), [
            'display_name' => 'Custom Renamed Claude',
            'context_window' => 150000,
            'enabled' => 1,
            'capabilities' => ['chat', 'vision'],
        ]);

        $response->assertRedirect(route('ai-settings.models.index'));
        $model->refresh();

        $this->assertSame('Custom Renamed Claude', $model->display_name);
        $this->assertSame(150000, $model->context_window);
        $this->assertContains('display_name', $model->overrides);
        $this->assertContains('context_window', $model->overrides);
    }

    public function test_sync_provider_models_preserves_overrides(): void
    {
        $service = app(ModelManagementInterface::class);

        // Tworzymy model z chronionym display_name w overrides
        $model = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'claude-3-5-sonnet-20241022',
            'display_name' => 'My Protected Claude Name',
            'context_window' => 100000,
            'capabilities' => ['chat'],
            'enabled' => true,
            'overrides' => ['display_name'],
        ]);

        $remoteModels = [
            [
                'model_key' => 'claude-3-5-sonnet-20241022',
                'display_name' => 'Remote Provider Name',
                'context_window' => 200000,
                'capabilities' => ['chat', 'vision', 'tools'],
            ],
        ];

        $result = $service->syncProviderModels($this->provider, $remoteModels);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['preserved_overrides']);

        $model->refresh();
        // display_name pozostaje niezmienione dzięki overrides
        $this->assertSame('My Protected Claude Name', $model->display_name);
        // context_window nie było chronione, więc zostało zaktualizowane
        $this->assertSame(200000, $model->context_window);
    }

    public function test_reset_overrides_clears_overrides_array(): void
    {
        $model = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'claude-test',
            'display_name' => 'Custom Name',
            'context_window' => 100000,
            'enabled' => true,
            'overrides' => ['display_name', 'context_window'],
        ]);

        $response = $this->actingAs($this->admin)->post(route('ai-settings.models.reset', $model));

        $response->assertRedirect(route('ai-settings.models.index'));
        $model->refresh();
        $this->assertEmpty($model->overrides);
    }

    public function test_cannot_delete_model_when_agent_depends_on_it(): void
    {
        $model = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'claude-dependent',
            'display_name' => 'Dependent Claude',
            'context_window' => 100000,
            'enabled' => true,
        ]);

        Agent::create([
            'name' => 'Security Agent',
            'slug' => 'security-agent',
            'runtime_type' => 'process',
            'primary_model' => 'claude-dependent',
            'system_prompt' => 'You are a test agent.',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('ai-settings.models.destroy', $model));

        $response->assertRedirect(route('ai-settings.models.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('models', ['id' => $model->id]);
    }
}
