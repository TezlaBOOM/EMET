<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class I18nCompletenessTest extends TestCase
{
    public function test_lang_audit_artisan_command_passes_with_strict_mode(): void
    {
        $this->artisan('lang:audit --strict')
            ->expectsOutputToContain('Audyt I18n zakończony pomyślnie')
            ->assertExitCode(0);
    }

    public function test_skills_submenu_translation_is_properly_resolved(): void
    {
        App::setLocale('pl');
        $this->assertSame('Skille', __('modules.agents.submenu.skills'));
        $this->assertSame('Skille', __('agents.sub_skills'));

        App::setLocale('en');
        $this->assertSame('Skills', __('modules.agents.submenu.skills'));
        $this->assertSame('Skills', __('agents.sub_skills'));
    }

    public function test_all_module_json_menu_titles_are_translated(): void
    {
        $modulesPath = base_path('modules');
        $this->assertTrue(File::isDirectory($modulesPath));

        foreach (File::directories($modulesPath) as $moduleDir) {
            $manifestPath = $moduleDir.'/module.json';
            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);
            $this->assertIsArray($manifest, 'Manifest in '.$moduleDir.' must be valid JSON');

            if (! empty($manifest['menu1']['title']) && str_contains($manifest['menu1']['title'], '.')) {
                $key = $manifest['menu1']['title'];
                $this->assertTrue(Lang::has($key, 'pl'), "Missing PL translation for menu1 key {$key}");
                $this->assertTrue(Lang::has($key, 'en'), "Missing EN translation for menu1 key {$key}");
            }

            if (! empty($manifest['menu2']) && is_array($manifest['menu2'])) {
                foreach ($manifest['menu2'] as $subItem) {
                    if (! empty($subItem['title']) && str_contains($subItem['title'], '.')) {
                        $key = $subItem['title'];
                        $this->assertTrue(Lang::has($key, 'pl'), "Missing PL translation for menu2 key {$key}");
                        $this->assertTrue(Lang::has($key, 'en'), "Missing EN translation for menu2 key {$key}");
                    }
                }
            }
        }
    }
}
