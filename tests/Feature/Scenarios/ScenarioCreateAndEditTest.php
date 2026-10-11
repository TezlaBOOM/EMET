<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Models\Scenario;
use App\Models\ScenarioVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioCreateAndEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_user_can_create_scenario_and_redirects_to_editor(): void
    {
        $response = $this->actingAs($this->admin)->post('/scenarios', [
            'name' => 'Mój Pierwszy Scenariusz',
            'description' => 'Opis testowego scenariusza',
        ]);

        $this->assertDatabaseHas('scenarios', [
            'name' => 'Mój Pierwszy Scenariusz',
            'slug' => 'moj-pierwszy-scenariusz',
            'status' => Scenario::STATUS_DRAFT,
        ]);

        $scenario = Scenario::where('slug', 'moj-pierwszy-scenariusz')->firstOrFail();
        $response->assertRedirect(route('scenarios.editor', $scenario));

        $this->assertNotNull($scenario->current_version_id);
        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'version' => 1,
            'draft' => true,
        ]);

        // Sprawdzenie, czy edytor otwiera się pomyślnie bez błędu 500
        $editorResponse = $this->actingAs($this->admin)->get(route('scenarios.editor', $scenario));
        $editorResponse->assertStatus(200);
        $editorResponse->assertSee('Mój Pierwszy Scenariusz');
    }

    public function test_slug_collision_handled_gracefully(): void
    {
        // Utworzenie pierwszego scenariusza
        $this->actingAs($this->admin)->post('/scenarios', [
            'name' => 'Scenariusz Raportu',
        ])->assertStatus(302);

        // Utworzenie drugiego scenariusza o tej samej nazwie
        $secondResponse = $this->actingAs($this->admin)->post('/scenarios', [
            'name' => 'Scenariusz Raportu',
        ]);

        $secondResponse->assertStatus(302);
        $this->assertDatabaseCount('scenarios', 2);

        $first = Scenario::where('slug', 'scenariusz-raportu')->first();
        $second = Scenario::where('slug', 'scenariusz-raportu-1')->first();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_editor_renders_defensively_if_graph_is_empty(): void
    {
        $scenario = Scenario::create([
            'name' => 'Pusty Scenariusz',
            'slug' => 'pusty-scenariusz',
            'status' => Scenario::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $version = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => 1,
            'graph' => [],
            'draft' => true,
        ]);

        $scenario->update(['current_version_id' => $version->id]);

        $response = $this->actingAs($this->admin)->get(route('scenarios.editor', $scenario));
        $response->assertStatus(200);
    }
}
