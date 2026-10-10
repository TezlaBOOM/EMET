<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsFirstSetupCommand extends Command
{
    protected $signature = 'docs:first-setup {--build : Kompiluj i zapisz docs/FIRST_SETUP.md}';

    protected $description = 'Kompilacja deterministycznego dokumentu docs/FIRST_SETUP.md z fragmentów modułów (v1.5.0)';

    public function handle(ModuleManager $moduleManager): int
    {
        $this->info('Kompilowanie podręcznika pierwszej konfiguracji AgentHub v1.5.0...');

        $targetFile = base_path('docs/FIRST_SETUP.md');
        $modules = $moduleManager->enabled()->sortBy('order');

        $content = "# Podręcznik Pierwszej Konfiguracji AgentHub (Wydanie 1.5.0)\n\n";
        $content .= "> Niniejszy dokument jest generowany automatycznie poleceniem `php artisan docs:first-setup --build`.\n";
        $content .= '> Ostatnia aktualizacja: '.now()->format('Y-m-d H:i:s')."\n\n";
        $content .= "## Spis treści\n\n";

        $sections = [];

        foreach ($modules as $mod) {
            $slug = $mod['slug'];
            $name = $mod['name'];
            $docPath = base_path("modules/{$name}/docs/first-setup.md");

            if (! File::exists($docPath)) {
                // Alternatywna ścieżka z nazwy katalogu
                $dirs = File::directories(base_path('modules'));
                foreach ($dirs as $dir) {
                    if (strtolower(basename($dir)) === strtolower($slug)) {
                        $docPath = "{$dir}/docs/first-setup.md";
                        break;
                    }
                }
            }

            if (File::exists($docPath)) {
                $docBody = File::get($docPath);
                $sections[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'body' => $docBody,
                ];
                $content .= "- [Moduł {$name}](#moduł-".strtolower($slug).")\n";
            }
        }

        $content .= "\n---\n\n";

        foreach ($sections as $sec) {
            $content .= '<a name="moduł-'.strtolower($sec['slug'])."\"></a>\n\n";
            $content .= '## Moduł: '.$sec['name']."\n\n";
            $content .= trim($sec['body'])."\n\n---\n\n";
        }

        File::ensureDirectoryExists(dirname($targetFile));
        File::put($targetFile, $content);

        $this->info("✓ Wygenerowano podręcznik w: {$targetFile}");
        $this->line('  Dołączono sekcje z '.count($sections).' modułów.');

        return 0;
    }
}
