<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsCheckCommand extends Command
{
    protected $signature = 'docs:check {--strict : Wymusza bezwzględną obecność sekcji [Unreleased]}';

    protected $description = 'Weryfikuje spójność dokumentacji modułów, CHANGELOG.md oraz poprawność linków lokalnych';

    public function handle(): int
    {
        $this->info('Sprawdzanie spójności dokumentacji AgentHub...');
        $hasErrors = false;

        // 1. Sprawdzenie CHANGELOG.md
        $changelogPath = base_path('CHANGELOG.md');
        if (! File::exists($changelogPath)) {
            $this->error('BŁĄD: Brak pliku CHANGELOG.md w głównym katalogu projektu.');
            $hasErrors = true;
        } else {
            $changelogContent = File::get($changelogPath);
            $hasUnreleased = str_contains($changelogContent, '## [Unreleased]');
            $hasV150 = str_contains($changelogContent, '## [1.5.0]');

            if ($this->option('strict') && ! $hasUnreleased) {
                $this->error('BŁĄD: CHANGELOG.md nie zawiera aktywnej sekcji ## [Unreleased].');
                $hasErrors = true;
            } elseif (! $hasUnreleased && ! $hasV150) {
                $this->error('BŁĄD: CHANGELOG.md nie zawiera sekcji ## [Unreleased] ani ## [1.5.0].');
                $hasErrors = true;
            } else {
                $this->line('  ✓ CHANGELOG.md zawiera wymaganą sekcję wydania.');
            }
        }

        // 2. Sprawdzenie modułów
        $modulesPath = base_path('modules');
        if (File::isDirectory($modulesPath)) {
            $moduleDirs = File::directories($modulesPath);

            foreach ($moduleDirs as $moduleDir) {
                $moduleName = basename($moduleDir);
                $manifestPath = $moduleDir.'/module.json';

                if (! File::exists($manifestPath)) {
                    $this->error("BŁĄD: Moduł {$moduleName} nie posiada pliku manifestu module.json.");
                    $hasErrors = true;

                    continue;
                }

                $manifest = json_decode(File::get($manifestPath), true);
                if (! is_array($manifest)) {
                    $this->error("BŁĄD: Manifest module.json w {$moduleName} zawiera niepoprawny format JSON.");
                    $hasErrors = true;

                    continue;
                }

                foreach (['name', 'slug', 'version', 'description'] as $reqField) {
                    if (empty($manifest[$reqField])) {
                        $this->error("BŁĄD: Manifest {$moduleName}/module.json nie posiada wymaganego pola '{$reqField}'.");
                        $hasErrors = true;
                    }
                }

                $this->line("  ✓ Moduł {$moduleName}: manifest poprawny.");
            }
        }

        // 3. Sprawdzenie linków lokalnych w plikach dokumentacji docs/
        $docsPath = base_path('docs');
        if (File::isDirectory($docsPath)) {
            $docFiles = File::allFiles($docsPath);
            foreach ($docFiles as $docFile) {
                if ($docFile->getExtension() !== 'md') {
                    continue;
                }

                $content = File::get($docFile->getRealPath());
                // Wyszukiwanie linków Markdown [text](relative_path.md)
                preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $target = $match[2];
                    // Pomijamy linki zewnętrzne, maile oraz kotwice
                    if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://') || str_starts_with($target, 'mailto:') || str_starts_with($target, '#')) {
                        continue;
                    }

                    // Usuwamy ew. kotwicę z linku lokalnego
                    $targetPathOnly = explode('#', $target)[0];
                    if ($targetPathOnly === '') {
                        continue;
                    }

                    $resolved = realpath(dirname($docFile->getRealPath()).'/'.$targetPathOnly);
                    if ($resolved === false || ! file_exists($resolved)) {
                        $this->warn("OSTRZEŻENIE: Martwy link lokalny w {$docFile->getRelativePathname()}: {$target}");
                    }
                }
            }
        }

        if ($hasErrors) {
            $this->error('Weryfikacja dokumentacji zakończona niepowodzeniem.');

            return self::FAILURE;
        }

        $this->info('Wszystkie weryfikacje dokumentacji zakończone pomyślnie! ✓');

        return self::SUCCESS;
    }
}
