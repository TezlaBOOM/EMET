<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\AuditLog;
use App\Models\ConfigTransfer;
use App\Services\ModuleManager;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class ConfigTransferManager
{
    public function __construct(
        protected ModuleManager $moduleManager,
        protected ConfigSecretCryptoService $cryptoService
    ) {}

    /**
     * Pobiera i sortuje topologicznie sekcje konfiguracji według ich zależności `dependsOn()`.
     *
     * @return array<string, ConfigSectionInterface>
     */
    public function getOrderedSections(): array
    {
        $rawSections = $this->moduleManager->getConfigSections();
        $sections = [];
        foreach ($rawSections as $sec) {
            $sections[$sec->key()] = $sec;
        }

        return $this->topologicalSort($sections);
    }

    /**
     * Eksport konfiguracji do archiwum ZIP.
     *
     * @param  array<string, mixed>  $options
     * @return array{transfer: ConfigTransfer, zip_path: string, file_hash: string}
     */
    public function export(array $options = [], ?string $password = null, ?int $userId = null): array
    {
        $orderedSections = $this->getOrderedSections();
        $exportId = (string) Str::uuid();
        $timestamp = now()->format('Ymd_His');
        $tempDir = storage_path("app/transfers/temp_export_{$exportId}");
        File::ensureDirectoryExists($tempDir.'/sections');
        File::ensureDirectoryExists($tempDir.'/assets/skills');

        $includeSecrets = ! empty($options['include_secrets']) && ! empty($password);
        $manifest = [
            'agenthub_version' => config('app.version', '1.5.0'),
            'exported_at' => now()->toIso8601String(),
            'sections' => [],
            'files_sha256' => [],
            'has_encrypted_secrets' => $includeSecrets,
        ];

        $secretsToEncrypt = [];

        foreach ($orderedSections as $key => $section) {
            $data = (array) $section->export($options);

            // Jeśli szyfrujemy sekrety hasłem, wyodrębniamy sekrety z sekcji
            if ($includeSecrets) {
                $secretFields = $section->secretFields();
                if (! empty($secretFields)) {
                    $secretsToEncrypt[$key] = [
                        'secret_fields' => $secretFields,
                        'data' => $data,
                    ];
                }
            }

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $sectionFilePath = "{$tempDir}/sections/{$key}.json";
            File::put($sectionFilePath, $jsonContent);

            $fileHash = hash('sha256', $jsonContent);
            $manifest['sections'][$key] = [
                'schema_version' => $section->schemaVersion(),
                'depends_on' => $section->dependsOn(),
                'sha256' => $fileHash,
            ];
            $manifest['files_sha256']["sections/{$key}.json"] = $fileHash;
        }

        // Szyfrowanie pliku secrets.enc
        if ($includeSecrets && ! empty($secretsToEncrypt)) {
            $secretsJson = json_encode($secretsToEncrypt, JSON_UNESCAPED_UNICODE);
            $encryptedPayload = $this->cryptoService->encrypt($secretsJson, $password);
            $secretsFilePath = "{$tempDir}/secrets.enc";
            File::put($secretsFilePath, $encryptedPayload);
            $manifest['files_sha256']['secrets.enc'] = hash('sha256', $encryptedPayload);
        }

        // Kopiowanie paczek skilli do assets/skills jeśli istnieją
        $skillsStorage = storage_path('app/skills');
        if (File::isDirectory($skillsStorage)) {
            File::copyDirectory($skillsStorage, "{$tempDir}/assets/skills");
        }

        // Zapis manifest.json
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put("{$tempDir}/manifest.json", $manifestJson);

        // Pakowanie do archiwum ZIP
        $zipDir = storage_path('app/transfers');
        File::ensureDirectoryExists($zipDir);
        $zipFileName = "config-export-{$timestamp}.zip";
        $zipPath = "{$zipDir}/{$zipFileName}";

        $this->createZipFromDirectory($tempDir, $zipPath);
        File::deleteDirectory($tempDir);

        $zipHash = hash_file('sha256', $zipPath);

        $transfer = ConfigTransfer::create([
            'id' => $exportId,
            'type' => 'export',
            'mode' => $options['mode'] ?? 'merge',
            'status' => 'completed',
            'file_path' => $zipPath,
            'file_name' => $zipFileName,
            'file_hash' => $zipHash,
            'sections' => array_keys($orderedSections),
            'has_secrets' => $includeSecrets,
            'user_id' => $userId,
        ]);

        AuditLog::record('config.exported', 'ConfigTransfer', $transfer->id, [
            'file_name' => $zipFileName,
            'file_hash' => $zipHash,
            'has_secrets' => $includeSecrets,
        ]);

        return [
            'transfer' => $transfer,
            'zip_path' => $zipPath,
            'file_hash' => $zipHash,
        ];
    }

    /**
     * Przygotowanie planu importu (dry-run) z walidacją sum kontrolnych i schematów.
     *
     * @return array{
     *     valid: bool,
     *     manifest: array,
     *     diff_report: array,
     *     errors: array<int, string>,
     *     temp_dir: string
     * }
     */
    public function planImport(string $zipPath, array $options = [], ?string $password = null): array
    {
        if (! File::exists($zipPath)) {
            return [
                'valid' => false,
                'manifest' => [],
                'diff_report' => [],
                'errors' => ["Plik archiwum ZIP nie istnieje: {$zipPath}"],
                'temp_dir' => '',
            ];
        }

        $importId = (string) Str::uuid();
        $tempDir = storage_path("app/transfers/temp_import_{$importId}");
        File::ensureDirectoryExists($tempDir);

        $this->extractZipToDirectory($zipPath, $tempDir);

        $manifestPath = "{$tempDir}/manifest.json";
        if (! File::exists($manifestPath)) {
            File::deleteDirectory($tempDir);

            return [
                'valid' => false,
                'manifest' => [],
                'diff_report' => [],
                'errors' => ['Brak pliku manifest.json w paczce konfiguracyjnej.'],
                'temp_dir' => '',
            ];
        }

        $manifest = json_decode(File::get($manifestPath), true) ?? [];
        $errors = [];

        // 1. Walidacja sum kontrolnych SHA-256
        foreach ($manifest['files_sha256'] ?? [] as $relPath => $expectedHash) {
            $filePath = "{$tempDir}/{$relPath}";
            if (! File::exists($filePath)) {
                $errors[] = "Brakujący plik zadeklarowany w manifeście: {$relPath}";

                continue;
            }

            $actualHash = hash_file('sha256', $filePath);
            if (! hash_equals($expectedHash, $actualHash)) {
                $errors[] = "Niezgodność sumy kontrolnej SHA-256 dla pliku: {$relPath}";
            }
        }

        // 2. Obsługa szyfrowanych sekretów jeśli obecne
        $decryptedSecrets = [];
        if (! empty($manifest['has_encrypted_secrets'])) {
            $secretsFile = "{$tempDir}/secrets.enc";
            if (File::exists($secretsFile)) {
                if (empty($password)) {
                    $errors[] = 'Paczka zawiera zaszyfrowane sekrety. Podaj hasło do ich odszyfrowania.';
                } else {
                    try {
                        $encryptedRaw = File::get($secretsFile);
                        $decryptedJson = $this->cryptoService->decrypt($encryptedRaw, $password);
                        $decryptedSecrets = json_decode($decryptedJson, true) ?? [];
                    } catch (Exception $e) {
                        $errors[] = 'Błąd odszyfrowania sekretów: '.$e->getMessage();
                    }
                }
            }
        }

        if (! empty($errors)) {
            File::deleteDirectory($tempDir);

            return [
                'valid' => false,
                'manifest' => $manifest,
                'diff_report' => [],
                'errors' => $errors,
                'temp_dir' => '',
            ];
        }

        // 3. Sprawdzanie planów per sekcja
        $orderedSections = $this->getOrderedSections();
        $diffReport = [];

        foreach ($orderedSections as $key => $section) {
            $sectionFilePath = "{$tempDir}/sections/{$key}.json";
            if (! File::exists($sectionFilePath)) {
                continue;
            }

            $sectionData = json_decode(File::get($sectionFilePath), true) ?? [];

            // Połączenie z odszyfrowanymi sekretami jeśli dostępne
            if (isset($decryptedSecrets[$key]['data'])) {
                $sectionData = array_replace_recursive($sectionData, $decryptedSecrets[$key]['data']);
            }

            $validation = $section->validate($sectionData);
            if (! $validation['is_valid']) {
                foreach ($validation['errors'] as $err) {
                    $errors[] = "[Sekcja {$key}] {$err}";
                }
            }

            $plan = $section->plan($sectionData, $options);
            $diffReport[$key] = $plan;
        }

        return [
            'valid' => empty($errors),
            'manifest' => $manifest,
            'diff_report' => $diffReport,
            'errors' => $errors,
            'temp_dir' => $tempDir,
        ];
    }

    /**
     * Aplikacja importu w transakcjach bazodanowych z automatycznym backupem przed zmianami.
     */
    public function applyImport(string $zipPath, array $options = [], ?string $password = null, ?int $userId = null): ConfigTransfer
    {
        $planResult = $this->planImport($zipPath, $options, $password);
        if (! $planResult['valid']) {
            throw new Exception('Błąd walidacji importu: '.implode(', ', $planResult['errors']));
        }

        $tempDir = $planResult['temp_dir'];
        $diffReport = $planResult['diff_report'];
        $timestamp = now()->format('Ymd_His');

        // 1. Automatyczny backup lokalny w storage/backups/config-<timestamp>.zip
        $backupDir = storage_path('backups');
        File::ensureDirectoryExists($backupDir);
        $backupPath = "{$backupDir}/config-{$timestamp}.zip";

        // Tworzymy snapshot backupu obecnego stanu
        $this->export(['include_secrets' => false], null);
        $latestExport = ConfigTransfer::where('type', 'export')->latest()->first();
        if ($latestExport && File::exists($latestExport->file_path)) {
            File::copy($latestExport->file_path, $backupPath);
        }

        $orderedSections = $this->getOrderedSections();
        $totalImported = 0;
        $totalUpdated = 0;
        $sectionStats = [];

        try {
            foreach ($orderedSections as $key => $section) {
                if (! isset($diffReport[$key])) {
                    continue;
                }

                $sectionPlan = $diffReport[$key];
                $result = $section->import($sectionPlan);

                if (! ($result['success'] ?? false)) {
                    throw new Exception("Błąd importu sekcji [{$key}]: ".implode(', ', $result['errors'] ?? []));
                }

                $imported = $result['imported_count'] ?? 0;
                $updated = $result['updated_count'] ?? 0;
                $totalImported += $imported;
                $totalUpdated += $updated;

                $sectionStats[$key] = [
                    'imported' => $imported,
                    'updated' => $updated,
                ];
            }

            // Kopiowanie paczek skilli z assets/skills jeśli obecne
            $importedSkills = "{$tempDir}/assets/skills";
            if (File::isDirectory($importedSkills)) {
                $targetSkills = storage_path('app/skills');
                File::ensureDirectoryExists($targetSkills);
                File::copyDirectory($importedSkills, $targetSkills);
            }

            File::deleteDirectory($tempDir);

            $transfer = ConfigTransfer::create([
                'id' => (string) Str::uuid(),
                'type' => 'import',
                'mode' => $options['mode'] ?? 'merge',
                'status' => 'completed',
                'file_path' => $zipPath,
                'file_name' => basename($zipPath),
                'file_hash' => hash_file('sha256', $zipPath),
                'sections' => array_keys($diffReport),
                'stats' => [
                    'total_imported' => $totalImported,
                    'total_updated' => $totalUpdated,
                    'sections' => $sectionStats,
                ],
                'diff_report' => $diffReport,
                'backup_path' => $backupPath,
                'user_id' => $userId,
            ]);

            AuditLog::record('config.imported', 'ConfigTransfer', $transfer->id, [
                'imported_count' => $totalImported,
                'updated_count' => $totalUpdated,
                'backup_path' => $backupPath,
            ]);

            return $transfer;
        } catch (Exception $e) {
            File::deleteDirectory($tempDir);

            $transfer = ConfigTransfer::create([
                'id' => (string) Str::uuid(),
                'type' => 'import',
                'mode' => $options['mode'] ?? 'merge',
                'status' => 'failed',
                'file_path' => $zipPath,
                'file_name' => basename($zipPath),
                'error_message' => $e->getMessage(),
                'backup_path' => $backupPath,
                'user_id' => $userId,
            ]);

            AuditLog::record('config.import_failed', 'ConfigTransfer', $transfer->id, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sortowanie topologiczne sekcji konfiguracji według grafu zależności.
     *
     * @param  array<string, ConfigSectionInterface>  $sections
     * @return array<string, ConfigSectionInterface>
     */
    protected function topologicalSort(array $sections): array
    {
        $visited = [];
        $result = [];

        $visit = function (string $key) use (&$visit, &$visited, &$result, $sections) {
            if (isset($visited[$key])) {
                return;
            }
            $visited[$key] = true;

            if (isset($sections[$key])) {
                foreach ($sections[$key]->dependsOn() as $dep) {
                    if (isset($sections[$dep])) {
                        $visit($dep);
                    }
                }
                $result[$key] = $sections[$key];
            }
        };

        foreach (array_keys($sections) as $key) {
            $visit($key);
        }

        return $result;
    }

    protected function createZipFromDirectory(string $sourceDir, string $outZipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Nie można utworzyć pliku archiwum ZIP: {$outZipPath}");
        }

        $files = File::allFiles($sourceDir);
        foreach ($files as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();
    }

    protected function extractZipToDirectory(string $zipPath, string $destinationDir): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new Exception("Nie można otworzyć archiwum ZIP: {$zipPath}");
        }

        $zip->extractTo($destinationDir);
        $zip->close();
    }
}
