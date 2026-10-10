<?php

declare(strict_types=1);

namespace Tests\Feature\Skills;

use App\Models\Agent;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\User;
use App\Services\Skills\SkillPackageExtractor;
use App\Services\Skills\SkillPermissionGuard;
use App\Services\Skills\SkillSandboxService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Modules\Agents\Config\SkillsConfigSection;
use Tests\TestCase;
use ZipArchive;

class SkillsStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->tempDir = storage_path('framework/testing/skills_'.uniqid());
        if (! File::isDirectory($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_skill_package_extractor_verifies_checksum_and_extracts_files(): void
    {
        $zipPath = $this->tempDir.'/valid_skill.zip';
        $destPath = $this->tempDir.'/extracted';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $manifest = [
            'name' => 'Web Scraper Skill',
            'slug' => 'web-scraper',
            'version' => '1.2.0',
            'type' => 'tool',
            'requires' => ['internet'],
        ];
        $zip->addFromString('skill.json', json_encode($manifest));
        $zip->addFromString('main.php', '<?php echo "ok";');
        $zip->close();

        $extractor = app(SkillPackageExtractor::class);
        $checksum = $extractor->verifyChecksum($zipPath);

        $this->assertNotEmpty($checksum);

        // Poprawna suma kontrolna
        $extractor->verifyChecksum($zipPath, $checksum);

        // Błędna suma kontrolna rzuca wyjątek
        $this->expectException(InvalidArgumentException::class);
        $extractor->verifyChecksum($zipPath, 'invalid_hash_value');
    }

    public function test_skill_package_extractor_prevents_zip_slip(): void
    {
        $zipPath = $this->tempDir.'/evil_skill.zip';
        $destPath = $this->tempDir.'/extracted_evil';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('../../../evil.php', '<?php echo "evil";');
        $zip->close();

        $extractor = app(SkillPackageExtractor::class);

        $this->expectException(InvalidArgumentException::class);
        $extractor->extract($zipPath, $destPath);
    }

    public function test_skill_permission_guard_enforces_agent_capabilities(): void
    {
        $skill = Skill::create([
            'name' => 'Search Skill',
            'slug' => 'search-skill',
            'type' => 'tool',
            'requires' => ['internet', 'memory.read'],
        ]);

        $offlineAgent = Agent::create([
            'name' => 'Offline Agent',
            'slug' => 'offline-agent',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'off',
            'memory_collection_id' => null,
        ]);

        $capableAgent = Agent::create([
            'name' => 'Capable Agent',
            'slug' => 'capable-agent',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'allowlist',
            'memory_collection_id' => 99,
        ]);

        $guard = app(SkillPermissionGuard::class);

        $checkOffline = $guard->check($skill, $offlineAgent);
        $this->assertFalse($checkOffline['allowed']);
        $this->assertCount(2, $checkOffline['missing_capabilities']);

        $checkCapable = $guard->check($skill, $capableAgent);
        $this->assertTrue($checkCapable['allowed']);
        $this->assertEmpty($checkCapable['missing_capabilities']);

        // Wymuszenie wyjątku dla offline
        $this->expectException(InvalidArgumentException::class);
        $guard->enforce($skill, $offlineAgent);
    }

    public function test_skill_sandbox_executes_in_isolated_process(): void
    {
        $skill = Skill::create([
            'name' => 'Echo Skill',
            'slug' => 'echo-skill',
            'type' => 'tool',
            'requires' => [],
        ]);

        $sandbox = app(SkillSandboxService::class);
        $run = $sandbox->execute($skill, ['param' => 'hello']);

        $this->assertSame('success', $run->status);
        $this->assertGreaterThanOrEqual(0, $run->duration_ms);
        $this->assertDatabaseHas('skill_runs', [
            'id' => $run->id,
            'skill_id' => $skill->id,
            'status' => 'success',
        ]);
    }

    public function test_skills_config_section_round_trip(): void
    {
        $skill = Skill::create([
            'name' => 'Config Export Skill',
            'slug' => 'config-export-skill',
            'type' => 'tool',
            'requires' => ['internet'],
        ]);

        SkillVersion::create([
            'skill_id' => $skill->id,
            'version' => '1.0.0',
            'definition' => ['foo' => 'bar'],
        ]);

        $section = app(SkillsConfigSection::class);
        $this->assertSame('skills', $section->key());

        $exported = $section->export([]);
        $this->assertIsArray($exported['records']);
        $this->assertNotEmpty($exported['records']);

        $validation = $section->validate($exported);
        $this->assertTrue($validation['is_valid']);

        $plan = $section->plan($exported, ['mode' => 'merge']);
        $this->assertNotEmpty($plan['update']);
    }

    public function test_user_can_view_skills_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('agents.skills.index'))
            ->assertStatus(200);
    }
}
