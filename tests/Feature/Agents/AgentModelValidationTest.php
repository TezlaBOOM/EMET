<?php

declare(strict_types=1);

namespace Tests\Feature\Agents;

use App\Models\Agent;
use App\Models\LlmAccountPool;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentModelValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected LlmProvider $provider;

    protected LlmModel $activeModel;

    protected LlmModel $disabledModel;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'agents.manage']);
        $role->givePermissionTo($permission);
        $viewPerm = Permission::firstOrCreate(['name' => 'agents.view']);
        $role->givePermissionTo($viewPerm);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'driver' => 'openai',
            'is_active' => true,
        ]);

        $this->activeModel = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'gpt-4o',
            'display_name' => 'GPT-4o',
            'enabled' => true,
            'context_window' => 128000,
            'capabilities' => ['chat', 'tools'],
        ]);

        $this->disabledModel = LlmModel::create([
            'provider_id' => $this->provider->id,
            'model_key' => 'legacy-model',
            'display_name' => 'Legacy Model',
            'enabled' => false,
            'context_window' => 4096,
            'capabilities' => ['chat'],
        ]);
    }

    public function test_agent_can_be_created_with_valid_enabled_model(): void
    {
        $response = $this->actingAs($this->admin)->post(route('agents.store'), [
            'name' => 'Test Coder',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'temperature' => 0.7,
        ]);

        $response->assertRedirect(route('agents.index'));
        $this->assertDatabaseHas('agents', [
            'name' => 'Test Coder',
            'primary_model' => 'gpt-4o',
        ]);
    }

    public function test_agent_creation_fails_when_model_is_disabled(): void
    {
        $response = $this->actingAs($this->admin)->post(route('agents.store'), [
            'name' => 'Invalid Agent',
            'runtime_type' => 'native',
            'primary_model' => 'legacy-model',
            'temperature' => 0.7,
        ]);

        $response->assertSessionHasErrors('primary_model');
        $this->assertDatabaseMissing('agents', [
            'name' => 'Invalid Agent',
        ]);
    }

    public function test_agent_creation_fails_when_model_not_allowed_by_pool(): void
    {
        $pool = LlmAccountPool::create([
            'name' => 'Strict Pool',
            'slug' => 'strict-pool',
            'allowed_models' => ['claude-3-5-sonnet'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('agents.store'), [
            'name' => 'Pool Restricted Agent',
            'runtime_type' => 'native',
            'pool_id' => $pool->id,
            'primary_model' => 'gpt-4o',
            'temperature' => 0.7,
        ]);

        $response->assertSessionHasErrors('primary_model');
    }

    public function test_agent_index_shows_warning_when_primary_model_is_disabled(): void
    {
        $agent = Agent::create([
            'name' => 'Old Agent',
            'slug' => 'old-agent',
            'runtime_type' => 'native',
            'primary_model' => 'legacy-model',
            'temperature' => 0.7,
            'is_active' => true,
        ]);

        $this->assertFalse($agent->isModelAvailable());

        $response = $this->actingAs($this->admin)->get(route('agents.index'));

        $response->assertOk();
        $response->assertSee('Model niedostępny');
    }
}
