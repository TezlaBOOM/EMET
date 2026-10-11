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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

    public function test_user_can_view_skills_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('agents.skills.create'))
            ->assertStatus(200)
            ->assertSee('Nowy Skill')
            ->assertSee('Formularz ręczny')
            ->assertSee('Paczka ZIP');
    }

    public function test_user_can_create_skill_manually(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('agents.skills.store'), [
                'source_type' => 'manual',
                'name' => 'Custom Tool Skill',
                'type' => 'tool',
                'description' => 'A custom manual tool',
                'readme_md' => '# Documentation',
            ]);

        $skill = Skill::where('slug', 'custom-tool-skill')->first();
        $this->assertNotNull($skill);
        $this->assertSame('Custom Tool Skill', $skill->name);
        $this->assertSame('tool', $skill->type);
        $this->assertNotNull($skill->currentVersion);

        $response->assertRedirect(route('agents.skills.show', $skill));
        $response->assertSessionHas('success');
    }

    public function test_user_can_create_skill_manually_with_deduplicated_slug(): void
    {
        Skill::create([
            'name' => 'My Duplicate Tool',
            'slug' => 'my-duplicate-tool',
            'type' => 'tool',
        ]);

        $this->actingAs($this->admin)
            ->post(route('agents.skills.store'), [
                'source_type' => 'manual',
                'name' => 'My Duplicate Tool',
                'type' => 'tool',
            ]);

        $this->assertDatabaseHas('skills', [
            'slug' => 'my-duplicate-tool-2',
        ]);
    }

    public function test_user_can_import_skill_from_zip_package(): void
    {
        Storage::fake('skills');

        $zipPath = $this->tempDir.'/imported_skill.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $manifest = [
            'name' => 'Package Zip Skill',
            'slug' => 'package-zip-skill',
            'version' => '1.0.0',
            'type' => 'package',
            'description' => 'Imported via ZIP',
        ];
        $zip->addFromString('skill.json', json_encode($manifest));
        $zip->addFromString('index.js', 'console.log("hello");');
        $zip->close();

        $uploadedFile = new UploadedFile(
            $zipPath,
            'imported_skill.zip',
            'application/zip',
            null,
            true
        );

        $response = $this->actingAs($this->admin)
            ->post(route('agents.skills.store'), [
                'source_type' => 'zip',
                'package' => $uploadedFile,
            ]);

        $skill = Skill::where('slug', 'package-zip-skill')->first();
        $this->assertNotNull($skill);
        $this->assertSame('Package Zip Skill', $skill->name);
        $this->assertSame('package', $skill->type);
        $this->assertNotNull($skill->currentVersion);
        $this->assertNotEmpty($skill->currentVersion->package_path);

        $response->assertRedirect(route('agents.skills.show', $skill));
        $response->assertSessionHas('success');
    }

    public function test_manual_skill_validation_fails_when_fields_missing(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('agents.skills.store'), [
                'source_type' => 'manual',
                'name' => '',
                'type' => '',
            ]);

        $response->assertSessionHasErrors(['name', 'type']);
    }

    public function test_zip_skill_validation_fails_when_file_missing(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('agents.skills.store'), [
                'source_type' => 'zip',
            ]);

        $response->assertSessionHasErrors(['package']);
    }
}
