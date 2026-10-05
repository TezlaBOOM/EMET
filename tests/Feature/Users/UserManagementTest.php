<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@test.lan',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create([
            'email' => 'viewer@test.lan',
            'password' => Hash::make('password123'),
        ]);
        $this->viewer->assignRole('viewer');
    }

    public function test_guest_is_redirected_from_users_and_ai_settings(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->get('/ai-settings')->assertRedirect('/login');
    }

    public function test_viewer_without_permissions_receives_forbidden(): void
    {
        $this->actingAs($this->viewer)
            ->get('/users')
            ->assertForbidden();

        $this->actingAs($this->viewer)
            ->get('/ai-settings')
            ->assertForbidden();
    }

    public function test_admin_can_view_users_list(): void
    {
        $this->actingAs($this->admin)
            ->get('/users')
            ->assertOk()
            ->assertSee($this->admin->email)
            ->assertSee($this->viewer->email);
    }

    public function test_admin_can_create_user_and_audit_log_is_recorded(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'name' => 'Jan Kowalski',
            'email' => 'jan@kowalski.lan',
            'password' => 'secret123',
            'role' => 'operator',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'jan@kowalski.lan']);

        $user = User::where('email', 'jan@kowalski.lan')->first();
        $this->assertTrue($user->hasRole('operator'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->delete("/users/{$this->admin->id}");
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_another_user_and_audit_log_is_recorded(): void
    {
        $userToDelete = User::factory()->create(['email' => 'to-delete@test.lan']);

        $response = $this->actingAs($this->admin)->delete("/users/{$userToDelete->id}");
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deleted',
            'entity_type' => 'User',
            'entity_id' => (string) $userToDelete->id,
        ]);
    }

    public function test_admin_can_manage_ai_accounts_and_test_connection(): void
    {
        $provider = LlmProvider::create([
            'name' => 'OpenAI Test',
            'slug' => 'openai',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'is_active' => true,
        ]);

        // Dodanie konta przez formularz
        $response = $this->actingAs($this->admin)->post('/ai-settings/accounts', [
            'provider_id' => $provider->id,
            'name' => 'OpenAI Production',
            'api_key' => 'sk-test-key-mock',
            'organization_id' => 'org-123',
            'weight' => 20,
            'rpm_limit' => 60,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('llm_accounts', [
            'provider_id' => $provider->id,
            'name' => 'OpenAI Production',
        ]);

        $account = LlmAccount::where('name', 'OpenAI Production')->first();
        $this->assertNotNull($account);

        // Mock testu połączenia z OpenAI API
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o']]
            ], 200),
        ]);

        $testResponse = $this->actingAs($this->admin)->postJson("/ai-settings/accounts/{$account->id}/test");
        $testResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('active', $account->fresh()->current_status);
        $this->assertNotNull($account->fresh()->last_tested_at);
    }
}
