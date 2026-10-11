<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

class LangAuditCommand extends Command
{
    protected $signature = 'lang:audit {--strict : Zwraca kod błędu 1 w przypadku brakujących kluczy}';

    protected $description = 'Weryfikuje kompletność tłumaczeń w lang/pl i lang/en oraz zgodność z manifestami modułów';

    public function handle(): int
    {
        $this->info('Rozpoczynanie audytu spójności tłumaczeń I18n (PL/EN)...');

        $missingInPl = [];
        $missingInEn = [];
        $missingMenuKeys = [];
        $missingBladeKeys = [];

        $plPath = lang_path('pl');
        $enPath = lang_path('en');

        $plFiles = File::exists($plPath) ? File::files($plPath) : [];
        $enFiles = File::exists($enPath) ? File::files($enPath) : [];

        $plGroups = [];
        foreach ($plFiles as $file) {
            if ($file->getExtension() === 'php') {
                $group = $file->getBasename('.php');
                $plGroups[$group] = Arr::dot(require $file->getRealPath());
            }
        }

        $enGroups = [];
        foreach ($enFiles as $file) {
            if ($file->getExtension() === 'php') {
                $group = $file->getBasename('.php');
                $enGroups[$group] = Arr::dot(require $file->getRealPath());
            }
        }

        // 1. Sprawdzenie symetrii kluczy między PL a EN dla grup aplikacji
        $appGroups = array_unique(array_merge(array_keys($plGroups), array_keys($enGroups)));
        $frameworkGroups = ['validation', 'pagination', 'passwords'];

        foreach ($appGroups as $group) {
            if (in_array($group, $frameworkGroups, true)) {
                continue;
            }

            $plKeys = array_keys($plGroups[$group] ?? []);
            $enKeys = array_keys($enGroups[$group] ?? []);

            foreach ($plKeys as $k) {
                if (! in_array($k, $enKeys, true)) {
                    $missingInEn[] = "{$group}.{$k}";
                }
            }

            foreach ($enKeys as $k) {
                if (! in_array($k, $plKeys, true)) {
                    $missingInPl[] = "{$group}.{$k}";
                }
            }
        }

        // 2. Sprawdzenie manifestów module.json
        $modulesPath = base_path('modules');
        if (File::isDirectory($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $manifestPath = $moduleDir.'/module.json';
                if (! File::exists($manifestPath)) {
                    continue;
                }

                $manifest = json_decode(File::get($manifestPath), true);
                if (! is_array($manifest)) {
                    continue;
                }

                if (! empty($manifest['menu1']['title'])) {
                    $key = $manifest['menu1']['title'];
                    $this->validateMenuKey($key, $manifest['slug'] ?? basename($moduleDir), $missingMenuKeys);
                }

                if (! empty($manifest['menu2']) && is_array($manifest['menu2'])) {
                    foreach ($manifest['menu2'] as $subItem) {
                        if (! empty($subItem['title'])) {
                            $this->validateMenuKey($subItem['title'], $manifest['slug'] ?? basename($moduleDir), $missingMenuKeys);
                        }
                    }
                }
            }
        }

        // 3. Skanowanie szablonów Blade w poszukiwaniu __('group.key')
        $scanDirs = [
            resource_path('views'),
            base_path('modules'),
        ];

        foreach ($scanDirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            $bladeFiles = File::allFiles($dir);
            foreach ($bladeFiles as $bFile) {
                if (! str_ends_with($bFile->getFilename(), '.blade.php')) {
                    continue;
                }

                $content = File::get($bFile->getRealPath());
                // Dopasowanie __('key') oraz @lang('key')
                preg_match_all("/(?:__|@lang)\(['\"]([a-zA-Z0-9_\-\.]+?)['\"]\)/", $content, $matches);

                if (! empty($matches[1])) {
                    foreach ($matches[1] as $transKey) {
                        // Ignoruj dynamiczne klucze lub pojedyncze słowa bez kropki
                        if (! str_contains($transKey, '.')) {
                            continue;
                        }

                        $parts = explode('.', $transKey);
                        $grp = $parts[0];
                        if (in_array($grp, $frameworkGroups, true)) {
                            continue;
                        }

                        if (! Lang::has($transKey, 'pl')) {
                            $missingBladeKeys['pl'][$transKey][] = $bFile->getRelativePathname();
                        }
                        if (! Lang::has($transKey, 'en')) {
                            $missingBladeKeys['en'][$transKey][] = $bFile->getRelativePathname();
                        }
                    }
                }
            }
        }

        // Prezentacja wyników
        $hasErrors = false;

        if (! empty($missingInEn)) {
            $this->warn('Brakujące klucze w lang/en (obecne w lang/pl):');
            foreach ($missingInEn as $k) {
                $this->line("  - [EN] {$k}");
            }
            $hasErrors = true;
        }

        if (! empty($missingInPl)) {
            $this->warn('Brakujące klucze w lang/pl (obecne w lang/en):');
            foreach ($missingInPl as $k) {
                $this->line("  - [PL] {$k}");
            }
            $hasErrors = true;
        }

        if (! empty($missingMenuKeys)) {
            $this->warn('Brakujące lub nieprzetłumaczone klucze z module.json:');
            foreach ($missingMenuKeys as $item) {
                $this->line("  - [Moduł {$item['module']}] Klucz '{$item['key']}' brak w: ".implode(', ', $item['missing']));
            }
            $hasErrors = true;
        }

        if (! empty($missingBladeKeys['pl']) || ! empty($missingBladeKeys['en'])) {
            $this->warn('Brakujące klucze użyte w szablonach Blade:');
            foreach ($missingBladeKeys['pl'] ?? [] as $k => $locs) {
                $this->line("  - [PL] {$k} (użyte w: {$locs[0]})");
            }
            foreach ($missingBladeKeys['en'] ?? [] as $k => $locs) {
                $this->line("  - [EN] {$k} (użyte w: {$locs[0]})");
            }
            $hasErrors = true;
        }

        if (! $hasErrors) {
            $this->info('✓ Audyt I18n zakończony pomyślnie: Wszystkie klucze są w pełni przetłumaczone w PL i EN!');

            return self::SUCCESS;
        }

        if ($this->option('strict')) {
            $this->error('Audyt I18n wykrył braki w tłumaczeniach (--strict włączony).');

            return self::FAILURE;
        }

        $this->warn('Audyt I18n wykrył braki, ale wykonano w trybie ostrzegawczym.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{module: string, key: string, missing: array<string>}>  $missingMenuKeys
     */
    private function validateMenuKey(string $key, string $module, array &$missingMenuKeys): void
    {
        // Jeśli klucz to surowy tekst bez kropki, to prawdopodobnie etykieta na sztywno
        if (! str_contains($key, '.')) {
            return;
        }

        $missing = [];
        if (! Lang::has($key, 'pl')) {
            $missing[] = 'PL';
        }
        if (! Lang::has($key, 'en')) {
            $missing[] = 'EN';
        }

        if (! empty($missing)) {
            $missingMenuKeys[] = [
                'module' => $module,
                'key' => $key,
                'missing' => $missing,
            ];
        }
    }
}
