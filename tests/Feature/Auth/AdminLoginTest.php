<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_admin_seeder_creates_default_user_with_admin_role(): void
    {
        $admin = User::where('email', 'admin@admin.lan')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Administrator', $admin->name);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_seeders_are_fully_idempotent(): void
    {
        // Ponowne uruchomienie nie może rzucać błędów ani tworzyć duplikatów
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@admin.lan')->count());
    }

    public function test_admin_can_log_in_with_default_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@admin.lan',
            'password' => 'admin',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@admin.lan',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_dashboard_and_see_7_block_layout(): void
    {
        /** @var User $admin */
        $admin = User::where('email', 'admin@admin.lan')->first();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);

        // Blok 1: Logo & Nazwa aplikacji
        $response->assertSee('AgentHub');

        // Blok 2: Menu 1 (Dashboard)
        $response->assertSee('Dashboard');

        // Blok 3: Wyloguj
        $response->assertSee(__('auth.logout'));

        // Blok 5: Profil zalogowanego użytkownika
        $response->assertSee($admin->name);

        // Blok 6: Menu 2 (Podkategorie)
        $response->assertSee('Przegląd');
        $response->assertSee('Tokeny');
        $response->assertSee('Wydajność');
        $response->assertSee('Koszty');

        // Blok 7: Obszar roboczy
        $response->assertSee('Aktywni Agenci');
        $response->assertSee('Zużycie tokenów');
    }

    public function test_user_can_logout(): void
    {
        /** @var User $admin */
        $admin = User::where('email', 'admin@admin.lan')->first();

        $response = $this->actingAs($admin)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
