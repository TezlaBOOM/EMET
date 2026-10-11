<?php

declare(strict_types=1);

namespace Tests\Feature\AiSettings;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LmStudioIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected LlmProvider $lmStudioProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin-lm@test.lan',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->lmStudioProvider = LlmProvider::firstOrCreate(
            ['slug' => 'lmstudio'],
            [
                'name' => 'LM Studio (Lokalny)',
                'driver' => 'lmstudio',
                'base_url' => 'http://localhost:1234/v1',
                'default_model' => 'local-model',
                'is_active' => true,
            ]
        );
    }

    public function test_can_add_lm_studio_account_without_api_key_and_with_custom_base_url(): void
    {
        $response = $this->actingAs($this->admin)->post('/ai-settings/accounts', [
            'provider_id' => $this->lmStudioProvider->id,
            'name' => 'Mój LM Studio Mac',
            'api_key' => '',
            'custom_base_url' => 'http://192.168.0.25:1234/v1',
            'weight' => 15,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('llm_accounts', [
            'provider_id' => $this->lmStudioProvider->id,
            'name' => 'Mój LM Studio Mac',
            'custom_base_url' => 'http://192.168.0.25:1234/v1',
        ]);

        $account = LlmAccount::where('name', 'Mój LM Studio Mac')->first();
        $this->assertNotNull($account);
        $this->assertEquals('lm-studio', $account->api_key);
        $this->assertEquals('http://192.168.0.25:1234/v1', $account->getEffectiveBaseUrl());
    }

    public function test_can_update_provider_base_url_and_model(): void
    {
        $response = $this->actingAs($this->admin)->put("/ai-settings/providers/{$this->lmStudioProvider->id}", [
            'name' => 'LM Studio Server LAN',
            'base_url' => 'http://192.168.0.100:1234/v1',
            'default_model' => 'qwen2.5-coder-7b',
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('llm_providers', [
            'id' => $this->lmStudioProvider->id,
            'name' => 'LM Studio Server LAN',
            'base_url' => 'http://192.168.0.100:1234/v1',
            'default_model' => 'qwen2.5-coder-7b',
        ]);
    }

    public function test_can_test_connection_for_lm_studio_account(): void
    {
        $account = LlmAccount::create([
            'provider_id' => $this->lmStudioProvider->id,
            'name' => 'LM Studio Account',
            'api_key' => 'lm-studio',
            'custom_base_url' => 'http://localhost:1234/v1',
            'weight' => 10,
        ]);

        Http::fake([
            'http://localhost:1234/v1/models' => Http::response([
                'data' => [
                    ['id' => 'deepseek-coder-v2-lite', 'object' => 'model'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson("/ai-settings/accounts/{$account->id}/test");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('deepseek-coder-v2-lite', $response->json('message'));
        $this->assertEquals('active', $account->fresh()->current_status);
        $this->assertNotNull($account->fresh()->last_tested_at);
    }

    public function test_llm_gateway_routes_to_lm_studio_account(): void
    {
        $account = LlmAccount::create([
            'provider_id' => $this->lmStudioProvider->id,
            'name' => 'LM Studio Workstation',
            'api_key' => 'mock-lm-studio',
            'weight' => 10,
            'current_status' => 'active',
        ]);

        /** @var LlmGatewayInterface $gateway */
        $gateway = app(LlmGatewayInterface::class);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Napisz funkcję w PHP']],
            model: 'lmstudio-local-model',
        );

        $response = $gateway->complete($request, null, $account);

        $this->assertStringContainsString('Mocked LM Studio response', $response->content);
        $this->assertEquals($account->id, $response->accountId);
    }
}
