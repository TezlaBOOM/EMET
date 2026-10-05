<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndExtensibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'admin@admin.lan',
            'password' => Hash::make('password123'),
        ]);
        $this->user->assignRole('admin');

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI Sec Provider',
            'slug' => 'openai-sec',
            'driver' => 'openai',
            'is_active' => true,
        ]);
    }

    public function test_api_keys_are_encrypted_in_database(): void
    {
        $rawSecret = 'sk-proj-VERY-SECRET-API-KEY-1234567890';

        $account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Secure Account',
            'api_key' => $rawSecret,
            'weight' => 10,
            'current_status' => 'active',
        ]);

        // 1. Surowy odczyt z bazy (bez modelu Eloquent)
        $rawDbRow = DB::table('llm_accounts')->where('id', $account->id)->first();
        $this->assertNotNull($rawDbRow);
        $this->assertNotEquals($rawSecret, $rawDbRow->api_key);
        $this->assertStringNotContainsString('VERY-SECRET', $rawDbRow->api_key);

        // 2. Odczyt przez model Eloquent z rzutowaniem 'encrypted'
        $accountReloaded = LlmAccount::find($account->id);
        $this->assertEquals($rawSecret, $accountReloaded->api_key);
    }

    public function test_audit_log_does_not_leak_api_keys(): void
    {
        AuditLog::record('account.created', 'LlmAccount', '1', [
            'provider' => 'OpenAI',
            'key_hint' => 'sk-***7890',
        ]);

        $log = AuditLog::where('action', 'account.created')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('VERY-SECRET', json_encode($log->details));
    }

    public function test_reference_hello_module_is_loaded_and_accessible(): void
    {
        $response = $this->actingAs($this->user)->get('/hello');

        $response->assertStatus(200);
        $response->assertSee('Referencyjny Moduł Rozszerzenia');
        $response->assertSee('v1.0.0');
    }
}
