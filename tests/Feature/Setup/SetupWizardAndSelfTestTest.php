<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Agent;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupWizardAndSelfTestTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@admin.lan',
            'password' => Hash::make('admin'),
        ]);
        $this->admin->assignRole('admin');

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'driver' => 'openai',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_from_setup(): void
    {
        $this->get('/setup/step/1')->assertRedirect('/login');
    }

    public function test_admin_can_navigate_setup_steps(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $response = $this->actingAs($this->admin)->get("/setup/step/{$i}");
            $response->assertStatus(200);
            $response->assertSee(__('setup.title'));
        }
    }

    public function test_setup_save_and_skip_steps(): void
    {
        // Krok 1: Wybór języka
        $res1 = $this->actingAs($this->admin)->post('/setup/step/1', [
            'locale' => 'en',
            'theme' => 'dark',
        ]);
        $res1->assertRedirect('/setup/step/2');
        $this->assertEquals('en', session('locale'));

        // Krok 2: Pomijanie
        $res2 = $this->actingAs($this->admin)->post('/setup/step/2/skip');
        $res2->assertRedirect('/setup/step/3');

        // Krok 3: Silnik wektorowy
        $res3 = $this->actingAs($this->admin)->post('/setup/step/3', [
            'vector_driver' => 'qdrant',
        ]);
        $res3->assertRedirect('/setup/step/4');
        $this->assertEquals('qdrant', session('setup_vector_driver'));

        // Krok 4: Dodanie konta AI
        $res4 = $this->actingAs($this->admin)->post('/setup/step/4', [
            'provider_id' => $this->provider->id,
            'account_name' => 'Konto Setup',
            'api_key' => 'sk-mock-key-12345',
        ]);
        $res4->assertRedirect('/setup/step/5');
        $this->assertDatabaseHas('llm_accounts', [
            'name' => 'Konto Setup',
            'provider_id' => $this->provider->id,
        ]);

        // Krok 5: Pominięcie integracji
        $res5 = $this->actingAs($this->admin)->post('/setup/step/5/skip');
        $res5->assertRedirect('/setup/step/6');

        // Krok 6: Utworzenie pierwszego agenta
        $res6 = $this->actingAs($this->admin)->post('/setup/step/6', [
            'agent_name' => 'Kreator Asystent',
            'enable_memory' => '1',
        ]);
        $res6->assertRedirect('/setup/step/7');
        $this->assertDatabaseHas('agents', [
            'name' => 'Kreator Asystent',
        ]);

        // Krok 7: Zakończenie kreatora
        $res7 = $this->actingAs($this->admin)->post('/setup/step/7');
        $res7->assertRedirect('/dashboard');
        $this->assertTrue(Cache::get('agenthub_setup_completed', false));
    }

    public function test_selftest_command_execution(): void
    {
        $exitCode = Artisan::call('agenthub:selftest', ['--json' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('schema', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
        $this->assertArrayHasKey('vector_store', $data['checks']);
        $this->assertArrayHasKey('llm_gateway', $data['checks']);
        $this->assertArrayHasKey('runtime_adapters', $data['checks']);
        $this->assertArrayHasKey('admin_user', $data['checks']);

        $this->assertEquals('ok', $data['checks']['database']['status']);
        $this->assertEquals('ok', $data['checks']['schema']['status']);
        $this->assertEquals('ok', $data['checks']['runtime_adapters']['status']);
    }
}
