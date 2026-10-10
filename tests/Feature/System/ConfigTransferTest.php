<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\Agent;
use App\Models\AutoConfigProfile;
use App\Models\Scenario;
use App\Models\Skill;
use App\Models\User;
use App\Services\Config\ConfigSecretCryptoService;
use App\Services\Config\ConfigTransferManager;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class ConfigTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'system.manage', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@agenthub.test',
        ]);
        $this->adminUser->givePermissionTo('system.manage');
    }

    public function test_it_encrypts_and_decrypts_secrets_with_argon2id_and_xchacha20(): void
    {
        $crypto = new ConfigSecretCryptoService;
        $secretText = json_encode(['api_key' => 'sk-agenthub-super-secret-key-12345']);
        $password = 'SuperStrongPassword!2026';

        $encrypted = $crypto->encrypt($secretText, $password);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($secretText, $encrypted);

        // Poprawne hasło odszyfrowuje
        $decrypted = $crypto->decrypt($encrypted, $password);
        $this->assertEquals($secretText, $decrypted);

        // Błędne hasło rzuca wyjątek
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Niepoprawne hasło|integralność/');
        $crypto->decrypt($encrypted, 'WrongPassword456!');
    }

    public function test_it_exports_configuration_to_zip_with_manifest_and_sha256(): void
    {
        // Przygotowanie danych testowych
        Agent::create([
            'name' => 'Exportable Agent',
            'slug' => 'exportable-agent',
            'system_prompt' => 'You are a test agent',
            'model' => 'gpt-4o',
            'status' => 'active',
            'internet_mode' => 'allowlist',
            'context_mode' => 'stateful',
        ]);

        Skill::create([
            'name' => 'Exportable Skill',
            'slug' => 'exportable-skill',
            'type' => 'tool',
            'status' => 'active',
            'code' => 'function run() { return 1; }',
            'requires' => [],
        ]);

        AutoConfigProfile::create([
            'name' => 'Ollama Export Profile',
            'slug' => 'ollama-export-profile',
            'adapter_type' => 'ollama',
            'target_model' => 'llama3.2',
            'steps' => [['name' => 'init']],
            'is_active' => true,
        ]);

        /** @var ConfigTransferManager $manager */
        $manager = app(ConfigTransferManager::class);
        $result = $manager->export(['include_secrets' => false]);

        $zipPath = $result['zip_path'];
        $this->assertFileExists($zipPath);

        // Weryfikacja zawartości archiwum ZIP
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertNotFalse($zip->locateName('manifest.json'));

        $manifestContent = $zip->getFromName('manifest.json');
        $manifest = json_decode($manifestContent, true);
        $this->assertEquals('1.5.0', $manifest['agenthub_version']);
        $this->assertArrayHasKey('files_sha256', $manifest);

        $zip->close();
    }

    public function test_it_performs_dry_run_validation_and_diff_generation(): void
    {
        Agent::create([
            'name' => 'DryRun Agent',
            'slug' => 'dryrun-agent',
            'system_prompt' => 'Initial prompt',
            'model' => 'gpt-4o',
            'status' => 'active',
        ]);

        /** @var ConfigTransferManager $manager */
        $manager = app(ConfigTransferManager::class);
        $exportResult = $manager->export();

        $planResult = $manager->planImport($exportResult['zip_path'], ['mode' => 'merge']);

        $this->assertTrue($planResult['valid']);
        $this->assertArrayHasKey('manifest', $planResult);
        $this->assertArrayHasKey('diff_report', $planResult);
    }

    public function test_round_trip_export_and_import_preserves_application_state(): void
    {
        // 1. Tworzymy unikalne rekordy
        Agent::create([
            'name' => 'RoundTrip Agent',
            'slug' => 'roundtrip-agent',
            'system_prompt' => 'Unique prompt for round-trip testing',
            'model' => 'claude-3-5-sonnet',
            'status' => 'active',
            'internet_mode' => 'open',
            'context_mode' => 'stateless',
        ]);

        Skill::create([
            'name' => 'RoundTrip Skill',
            'slug' => 'roundtrip-skill',
            'type' => 'tool',
            'status' => 'active',
            'code' => 'function roundTrip() { return true; }',
            'requires' => ['internet'],
        ]);

        Scenario::create([
            'name' => 'RoundTrip Scenario',
            'slug' => 'roundtrip-scenario',
            'status' => 'published',
            'created_by' => $this->adminUser->id,
            'graph' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'start'],
                    ['id' => 'n2', 'type' => 'end'],
                ],
                'edges' => [],
            ],
        ]);

        /** @var ConfigTransferManager $manager */
        $manager = app(ConfigTransferManager::class);
        $exportResult = $manager->export();
        $zipPath = $exportResult['zip_path'];

        // 2. Czyścimy tabele (symulacja czystej instalacji)
        Agent::query()->delete();
        Skill::query()->forceDelete();
        Scenario::query()->forceDelete();

        $this->assertEquals(0, Agent::count());
        $this->assertEquals(0, Skill::withTrashed()->count());
        $this->assertEquals(0, Scenario::withTrashed()->count());

        // 3. Aplikujemy import
        $transfer = $manager->applyImport($zipPath, ['mode' => 'merge']);

        $this->assertEquals('completed', $transfer->status);
        $this->assertFileExists($transfer->backup_path);

        // 4. Potwierdzenie przywrócenia rekordów
        $restoredAgent = Agent::where('slug', 'roundtrip-agent')->first();
        $this->assertNotNull($restoredAgent);
        $this->assertEquals('open', $restoredAgent->internet_mode);
        $this->assertEquals('stateless', $restoredAgent->context_mode);

        $restoredSkill = Skill::where('slug', 'roundtrip-skill')->first();
        $this->assertNotNull($restoredSkill);
        $this->assertEquals('active', $restoredSkill->status);

        $restoredScenario = Scenario::where('slug', 'roundtrip-scenario')->first();
        $this->assertNotNull($restoredScenario);
        $this->assertEquals('published', $restoredScenario->status);
    }

    public function test_artisan_commands_export_and_import(): void
    {
        $exportZip = storage_path('app/transfers/test-artisan-export.zip');
        if (File::exists($exportZip)) {
            File::delete($exportZip);
        }

        $this->artisan('config:export', ['--path' => $exportZip])
            ->assertExitCode(0);

        $this->assertFileExists($exportZip);

        // Dry-run import
        $this->artisan('config:import', [
            'file' => $exportZip,
            '--dry-run' => true,
        ])->assertExitCode(0);

        // Live import
        $this->artisan('config:import', [
            'file' => $exportZip,
            '--mode' => 'merge',
        ])->assertExitCode(0);
    }
}
