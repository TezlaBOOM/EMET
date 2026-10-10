<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ModuleManager
{
    /** @var Collection<string, array> */
    protected Collection $modules;

    public function __construct()
    {
        $this->modules = collect();
        $this->discoverModules();
    }

    /**
     * Skanowanie katalogu modules/ w poszukiwaniu manifestów module.json
     */
    public function discoverModules(): void
    {
        $modulesPath = base_path('modules');

        if (! File::isDirectory($modulesPath)) {
            File::makeDirectory($modulesPath, 0755, true);
        }

        $manifests = File::glob($modulesPath.'/*/module.json');

        foreach ($manifests as $manifestPath) {
            $content = json_decode(File::get($manifestPath), true);
            if (is_array($content) && isset($content['slug'])) {
                $content['_path'] = dirname($manifestPath);
                $this->modules->put($content['slug'], $content);
            }
        }
    }

    /**
     * Zwraca wszystkie wykryte moduły
     *
     * @return Collection<string, array>
     */
    public function all(): Collection
    {
        return $this->modules;
    }

    /**
     * Zwraca tylko aktywne moduły posortowane wg kolejności
     *
     * @return Collection<string, array>
     */
    public function enabled(): Collection
    {
        return $this->modules
            ->filter(fn (array $module) => $module['enabled'] ?? true)
            ->sortBy(fn (array $module) => $module['order'] ?? 99);
    }

    /**
     * Zwraca pozycje Menu 1 przefiltrowane przez uprawnienia użytkownika
     */
    public function getMenu1(?User $user = null): array
    {
        return $this->enabled()
            ->filter(function (array $module) use ($user) {
                if (empty($module['permission'])) {
                    return true;
                }
                if (! $user) {
                    return false;
                }
                if ($user->hasRole('admin')) {
                    return true;
                }

                return $user->can($module['permission']);
            })
            ->map(function (array $module) {
                return [
                    'slug' => $module['slug'],
                    'name' => $module['name'],
                    'icon' => $module['icon'] ?? 'cube',
                    'route' => $module['menu1']['route'] ?? $module['slug'].'.index',
                    'order' => $module['menu1']['order'] ?? ($module['order'] ?? 99),
                ];
            })
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * Zwraca podkategorie Menu 2 dla wybranego aktywnego modułu
     */
    public function getMenu2(string $moduleSlug, ?User $user = null): array
    {
        $module = $this->modules->get($moduleSlug);

        if (! $module || empty($module['menu2']) || ! is_array($module['menu2'])) {
            return [];
        }

        return collect($module['menu2'])
            ->filter(function (array $subItem) use ($user) {
                if (empty($subItem['permission'])) {
                    return true;
                }
                if (! $user) {
                    return false;
                }
                if ($user->hasRole('admin')) {
                    return true;
                }

                return $user->can($subItem['permission']);
            })
            ->values()
            ->all();
    }

    /**
     * Zwraca zarejestrowane sekcje konfiguracyjne ze wszystkich aktywnych modułów.
     *
     * @return Collection<string, ConfigSectionInterface>
     */
    public function getConfigSections(): Collection
    {
        $sections = collect();

        foreach ($this->enabled() as $module) {
            $configured = $module['config_sections'] ?? [];
            if (! is_array($configured)) {
                $configured = [$configured];
            }
            if (! empty($module['config_section'])) {
                $configured[] = $module['config_section'];
            }

            foreach ($configured as $sectionClass) {
                if (is_string($sectionClass) && class_exists($sectionClass)) {
                    $instance = app($sectionClass);
                    if ($instance instanceof ConfigSectionInterface) {
                        $sections->put($instance->key(), $instance);
                    }
                }
            }
        }

        return $sections;
    }
}
