<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Contracts\Skills\FederatedSkillHubInterface;
use App\Http\Controllers\Controller;
use App\Models\FederatedRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FederatedSkillHubController extends Controller
{
    public function __construct(
        protected FederatedSkillHubInterface $hubService,
    ) {}

    public function index(): View
    {
        $registries = FederatedRegistry::latest()->get();

        return view('module-agents::hub', [
            'registries' => $registries,
        ]);
    }

    public function storeRegistry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'registry_url' => ['required', 'url', 'max:255'],
            'public_key_ed25519' => ['nullable', 'string', 'max:128'],
        ]);

        $pubKey = $validated['public_key_ed25519'] ?? '';
        if ($pubKey === '') {
            $keys = $this->hubService->generateKeypair();
            $pubKey = $keys['public_key_hex'];
        }

        FederatedRegistry::create([
            'name' => $validated['name'],
            'registry_url' => $validated['registry_url'],
            'public_key_ed25519' => $pubKey,
            'is_trusted' => true,
            'last_synced_at' => now(),
        ]);

        return redirect()
            ->route('agents.hub.index')
            ->with('status', __('agents.hub.registry_added'));
    }
}
