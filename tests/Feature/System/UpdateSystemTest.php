<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@admin.lan',
            'password' => Hash::make('secret123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_guest_cannot_access_system_updates_panel(): void
    {
        $this->get('/users/system')->assertRedirect('/login');
    }

    public function test_admin_can_view_system_updates_panel(): void
    {
        $response = $this->actingAs($this->admin)->get('/users/system');

        $version = trim((string) file_get_contents(base_path('VERSION')));
        $response->assertStatus(200);
        $response->assertSee(__('users.system_title'));
        $response->assertSee('v'.$version);
    }

    public function test_check_update_command_execution(): void
    {
        $version = trim((string) file_get_contents(base_path('VERSION')));
        $exitCode = Artisan::call('agenthub:check-update', ['--json' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertEquals($version, $data['current_version']);
        $this->assertEquals('stable', $data['channel']);
        $this->assertArrayHasKey('update_available', $data);
    }

    public function test_compat_check_script_execution(): void
    {
        $script = base_path('scripts/compat-check.sh');
        $this->assertFileExists($script);

        $output = [];
        $exitCode = 0;
        exec("bash {$script} 2>&1", $output, $exitCode);

        $this->assertEquals(0, $exitCode, 'Compat-check output: '.implode("\n", $output));
    }

    public function test_admin_can_trigger_compat_check_via_http(): void
    {
        $response = $this->actingAs($this->admin)->post('/users/system/compat-check');

        $response->assertSessionHas('success');
    }
}
