<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Models\Scenario;
use App\Models\ScenarioVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScenarioEditAndVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user2;

    protected Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $perms = ['scenarios.view', 'scenarios.manage', 'scenarios.run'];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p]);
            $role->givePermissionTo($perm);
        }

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->user2 = User::factory()->create(['name' => 'Editor Two']);
        $this->user2->assignRole('admin');

        $this->scenario = Scenario::create([
            'name' => 'Support Workflow',
            'slug' => 'support-workflow',
            'description' => 'Initial Description',
            'status' => Scenario::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $v1 = ScenarioVersion::create([
            'scenario_id' => $this->scenario->id,
            'version' => 1,
            'graph' => [
                'nodes' => [
                    'n1' => ['id' => 'n1', 'type' => 'start', 'name' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]]]],
                    'n2' => ['id' => 'n2', 'type' => 'end', 'name' => 'end', 'inputs' => ['input_1' => ['connections' => [['node' => 'n1', 'output' => 'output_1']]]]],
                ],
            ],
            'draft' => true,
        ]);
        $this->scenario->update(['current_version_id' => $v1->id]);
    }

    public function test_can_update_scenario_metadata(): void
    {
        $response = $this->actingAs($this->admin)->put(route('scenarios.update', $this->scenario), [
            'name' => 'Support Workflow 2.0',
            'description' => 'Updated Description',
            'status' => Scenario::STATUS_DRAFT,
        ]);

        $response->assertRedirect(route('scenarios.edit', $this->scenario));
        $this->scenario->refresh();
        $this->assertSame('Support Workflow 2.0', $this->scenario->name);
        $this->assertSame('Updated Description', $this->scenario->description);
    }

    public function test_saving_graph_on_published_scenario_creates_new_draft_version(): void
    {
        // 1. Opublikuj wersję 1
        $this->scenario->currentVersion->update(['draft' => false, 'published_at' => now()]);
        $this->scenario->update(['status' => Scenario::STATUS_PUBLISHED]);

        $newGraph = [
            'nodes' => [
                'n1' => ['id' => 'n1', 'type' => 'start', 'name' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]]]],
                'n2' => ['id' => 'n2', 'type' => 'end', 'name' => 'end', 'inputs' => ['input_1' => ['connections' => [['node' => 'n1', 'output' => 'output_1']]]]],
            ],
        ];

        // 2. Zapisz nowy graf przez saveGraph
        $response = $this->actingAs($this->admin)->postJson(route('scenarios.save', $this->scenario), [
            'graph' => $newGraph,
        ]);

        $response->assertOk();
        $this->scenario->refresh();

        // Powinna powstać wersja 2 jako draft, podczas gdy wersja 1 pozostaje niezmieniona
        $this->assertCount(2, $this->scenario->versions);
        $this->assertSame(2, $this->scenario->currentVersion->version);
        $this->assertTrue((bool) $this->scenario->currentVersion->draft);
    }

    public function test_concurrent_edit_locking_mechanism(): void
    {
        // Admin wchodzi do edytora - pobiera blokadę
        $response = $this->actingAs($this->admin)->get(route('scenarios.editor', $this->scenario));
        $response->assertOk();

        $this->scenario->refresh();
        $this->assertSame($this->admin->id, $this->scenario->locked_by);

        // Drugi użytkownik wchodzi do edytora - widzi ostrzeżenie o blokadzie przez Admin User
        $response2 = $this->actingAs($this->user2)->get(route('scenarios.editor', $this->scenario));
        $response2->assertOk();
        $response2->assertSee('Admin User');
        $response2->assertSee('Przejmij blokadę');

        // Drugi użytkownik przejmuje blokadę
        $lockResponse = $this->actingAs($this->user2)->post(route('scenarios.lock', $this->scenario));
        $lockResponse->assertRedirect();

        $this->scenario->refresh();
        $this->assertSame($this->user2->id, $this->scenario->locked_by);
    }

    public function test_can_restore_archived_version(): void
    {
        $v1Graph = $this->scenario->currentVersion->graph;

        // Utworzenie wersji 2
        $v2 = ScenarioVersion::create([
            'scenario_id' => $this->scenario->id,
            'version' => 2,
            'graph' => [
                'nodes' => [
                    'n1' => ['id' => 'n1', 'type' => 'start', 'name' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]]]],
                    'n2' => ['id' => 'n2', 'type' => 'end', 'name' => 'end', 'inputs' => ['input_1' => ['connections' => [['node' => 'n1', 'output' => 'output_1']]]]],
                ],
            ],
            'draft' => false,
            'published_at' => now(),
        ]);
        $this->scenario->update(['current_version_id' => $v2->id]);

        $v1 = $this->scenario->versions()->where('version', 1)->first();

        // Przywrócenie wersji 1
        $response = $this->actingAs($this->admin)->post(route('scenarios.restore-version', [
            'scenario' => $this->scenario,
            'version' => $v1,
        ]));

        $response->assertRedirect(route('scenarios.edit', $this->scenario));

        $this->scenario->refresh();
        // Została utworzona wersja 3 zawierająca graf z v1
        $this->assertSame(3, $this->scenario->currentVersion->version);
        $this->assertSame($v1Graph, $this->scenario->currentVersion->graph);
    }
}
