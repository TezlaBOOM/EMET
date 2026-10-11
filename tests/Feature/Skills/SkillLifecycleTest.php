<?php

declare(strict_types=1);

namespace Tests\Feature\Skills;

use App\Contracts\Skills\SkillLifecycleInterface;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SkillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected SkillLifecycleInterface $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $perms = [
            'skills.view',
            'skills.manage',
            'skills.edit',
            'skills.delete',
            'skills.purge',
        ];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p]);
            $role->givePermissionTo($perm);
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->lifecycle = app(SkillLifecycleInterface::class);
    }

    public function test_new_skill_is_unused_and_shows_red_flag_badge(): void
    {
        $skill = Skill::create([
            'name' => 'Standalone Tool',
            'slug' => 'standalone-tool',
            'type' => 'tool',
            'status' => 'active',
        ]);

        $this->assertTrue($this->lifecycle->isUnused($skill));

        $response = $this->actingAs($this->admin)->get(route('agents.skills.index'));

        $response->assertOk();
        $response->assertSee('🚩 Nieużywany');
        $response->assertSee('Standalone Tool');
    }

    public function test_skill_attached_to_agent_is_not_unused(): void
    {
        $skill = Skill::create([
            'name' => 'Web Search Tool',
            'slug' => 'web-search-tool',
            'type' => 'tool',
            'status' => 'active',
        ]);

        $agent = Agent::create([
            'name' => 'Researcher Agent',
            'slug' => 'researcher-agent',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'temperature' => 0.7,
        ]);

        AgentSkill::create([
            'agent_id' => $agent->id,
            'skill_id' => $skill->id,
            'name' => 'Web Search Tool',
            'driver' => $skill->slug,
            'is_enabled' => true,
        ]);

        $this->assertFalse($this->lifecycle->isUnused($skill));

        $deps = $this->lifecycle->checkDependencies($skill);
        $this->assertCount(1, $deps['agents']);
        $this->assertSame('Researcher Agent', $deps['agents'][0]['name']);
    }

    public function test_deleting_skill_with_active_dependencies_returns_conflict_409(): void
    {
        $skill = Skill::create([
            'name' => 'Critical Calculation',
            'slug' => 'critical-calc',
            'type' => 'tool',
            'status' => 'active',
        ]);

        $agent = Agent::create([
            'name' => 'Math Agent',
            'slug' => 'math-agent',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'temperature' => 0.5,
        ]);

        AgentSkill::create([
            'agent_id' => $agent->id,
            'skill_id' => $skill->id,
            'name' => 'Critical Calculation',
            'driver' => $skill->slug,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('agents.skills.destroy', $skill));

        $response->assertStatus(409);
        $this->assertNull($skill->fresh()->deleted_at);
    }

    public function test_safe_delete_moves_unused_skill_to_trash(): void
    {
        $skill = Skill::create([
            'name' => 'Unused Tool',
            'slug' => 'unused-tool',
            'type' => 'tool',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('agents.skills.destroy', $skill));

        $response->assertRedirect(route('agents.skills.index'));
        $this->assertNotNull($skill->fresh()->deleted_at);
        $this->assertSame($this->admin->id, $skill->fresh()->deleted_by);
    }

    public function test_updating_definition_creates_new_semver_version(): void
    {
        $skill = Skill::create([
            'name' => 'Versioned Skill',
            'slug' => 'versioned-skill',
            'type' => 'tool',
            'status' => 'active',
        ]);

        $v1 = SkillVersion::create([
            'skill_id' => $skill->id,
            'version' => '1.0.0',
            'definition' => ['step' => 1],
        ]);
        $skill->update(['current_version_id' => $v1->id]);

        $response = $this->actingAs($this->admin)->put(route('agents.skills.update', $skill), [
            'name' => 'Versioned Skill (Updated)',
            'type' => 'tool',
            'status' => 'active',
            'definition' => json_encode(['step' => 2, 'feature' => 'new']),
            'bump_type' => 'minor',
            'changelog' => 'Dodano krok 2',
        ]);

        $response->assertRedirect(route('agents.skills.show', $skill));

        $skill->refresh();
        $this->assertSame('Versioned Skill (Updated)', $skill->name);
        $this->assertSame('1.1.0', $skill->currentVersion->version);
        $this->assertSame('Dodano krok 2', $skill->currentVersion->changelog);
    }

    public function test_can_restore_and_purge_skill_from_trash(): void
    {
        $skill = Skill::create([
            'name' => 'Trashed Tool',
            'slug' => 'trashed-tool',
            'type' => 'tool',
            'status' => 'active',
        ]);

        // Soft delete
        $this->lifecycle->safeDelete($skill);
        $this->assertTrue($skill->fresh()->trashed());

        // Restore
        $response = $this->actingAs($this->admin)->post(route('agents.skills.restore', $skill->id));
        $response->assertRedirect(route('agents.skills.index'));
        $this->assertFalse($skill->fresh()->trashed());

        // Soft delete again and Purge permanently
        $this->lifecycle->safeDelete($skill);
        $response = $this->actingAs($this->admin)->delete(route('agents.skills.purge', $skill->id));
        $response->assertRedirect(route('agents.skills.index', ['trash' => 1]));

        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }
}
