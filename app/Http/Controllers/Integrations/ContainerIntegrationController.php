<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\AutoConfigProfile;
use App\Models\IntegrationContainer;
use App\Services\Integrations\ContainerAdoptionService;
use App\Services\Integrations\DockerSocketService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContainerIntegrationController extends Controller
{
    public function __construct(
        protected ContainerAdoptionService $adoptionService,
        protected DockerSocketService $dockerSocket
    ) {}

    public function index(Request $request): View
    {
        $containers = IntegrationContainer::latest('last_inspected_at')->get();
        $profiles = AutoConfigProfile::where('is_active', true)->get();
        $isSocketAvailable = $this->dockerSocket->isAvailable();

        return view('module-integrations::containers', [
            'containers' => $containers,
            'profiles' => $profiles,
            'isSocketAvailable' => $isSocketAvailable,
            'activeSubcategory' => 'containers',
        ]);
    }

    public function detect(): RedirectResponse
    {
        try {
            $discovered = $this->adoptionService->detectContainers();

            return back()->with('success', 'Wykryto '.count($discovered).' kontenerów Docker.');
        } catch (Exception $e) {
            return back()->with('error', 'Błąd podczas wykrywania kontenerów: '.$e->getMessage());
        }
    }

    public function adopt(Request $request, IntegrationContainer $container): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:observe,configure,managed'],
            'join_network' => ['nullable', 'boolean'],
        ]);

        try {
            $this->adoptionService->adoptContainer(
                $container,
                $validated['mode'],
                (bool) ($validated['join_network'] ?? true)
            );

            return back()->with('success', "Kontener [{$container->name}] został zaadoptowany w trybie [{$validated['mode']}].");
        } catch (Exception $e) {
            return back()->with('error', 'Błąd adopcji: '.$e->getMessage());
        }
    }

    public function release(IntegrationContainer $container): RedirectResponse
    {
        try {
            $this->adoptionService->releaseContainer($container);

            return back()->with('success', "Zwolniono kontener [{$container->name}] z adopcji.");
        } catch (Exception $e) {
            return back()->with('error', 'Błąd zwalniania kontenera: '.$e->getMessage());
        }
    }

    public function configure(Request $request, IntegrationContainer $container): RedirectResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        try {
            $result = $this->adoptionService->configureContainer($container, $validated['config']);

            return back()->with('success', "Pomyślnie zaktualizowano konfigurację kontenera [{$container->name}].");
        } catch (Exception $e) {
            return back()->with('error', 'Błąd zapisu konfiguracji: '.$e->getMessage());
        }
    }

    public function autoConfig(Request $request, IntegrationContainer $container): RedirectResponse
    {
        $validated = $request->validate([
            'profile_id' => ['required', 'exists:autoconfig_profiles,id'],
        ]);

        $profile = AutoConfigProfile::findOrFail($validated['profile_id']);

        try {
            $run = $this->adoptionService->runAutoConfig($container, $profile);
            if ($run->status === 'rolled_back') {
                return back()->with('error', 'Auto-konfiguracja nie powiodła się. Wykonano automatyczny rollback do snapshotu: '.$run->error_message);
            }

            return back()->with('success', "Auto-konfiguracja profilem [{$profile->name}] powiodła się!");
        } catch (Exception $e) {
            return back()->with('error', 'Błąd auto-konfiguracji: '.$e->getMessage());
        }
    }

    public function exec(Request $request, IntegrationContainer $container): RedirectResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->adoptionService->executeAllowlistedCommand($container, $validated['command']);

            return back()->with('success', 'Wykonano polecenie: '.($result['output'] ?? 'OK'));
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
