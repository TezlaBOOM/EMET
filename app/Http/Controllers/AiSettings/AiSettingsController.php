<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiSettings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Services\LlmConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    /**
     * Lista dostawców AI
     */
    public function index(): View
    {
        $providers = LlmProvider::withCount('accounts')->get();

        return view('module-ai-settings::index', compact('providers'));
    }

    /**
     * Lista kont i formularz zarządzania
     */
    public function accounts(): View
    {
        $accounts = LlmAccount::with('provider')->latest()->get();
        $providers = LlmProvider::where('is_active', true)->get();

        return view('module-ai-settings::accounts', compact('accounts', 'providers'));
    }

    /**
     * Zapis nowego konta dostawcy z szyfrowanym kluczem API
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:llm_providers,id'],
            'name' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'organization_id' => ['nullable', 'string', 'max:100'],
            'custom_base_url' => ['nullable', 'string', 'max:255'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'rpm_limit' => ['nullable', 'integer', 'min:1'],
            'tpm_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        if (empty($validated['api_key'])) {
            $provider = LlmProvider::find($validated['provider_id']);
            $validated['api_key'] = ($provider && $provider->driver === 'lmstudio') ? 'lm-studio' : 'local';
        }

        $account = LlmAccount::create($validated);

        AuditLog::record('llm_account.created', 'LlmAccount', (string) $account->id, [
            'provider_id' => $account->provider_id,
            'name' => $account->name,
        ]);

        return back()->with('success', __('ai.account_created'));
    }

    /**
     * Aktualizacja parametrów dostawcy (np. zmiana Base URL dla LM Studio lub domyślnego modelu)
     */
    public function updateProvider(Request $request, LlmProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'default_model' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $provider->update($validated);

        AuditLog::record('llm_provider.updated', 'LlmProvider', (string) $provider->id, [
            'name' => $provider->name,
            'base_url' => $provider->base_url,
            'default_model' => $provider->default_model,
        ]);

        return back()->with('success', __('ai.provider_updated', ['name' => $provider->name]));
    }

    /**
     * Usunięcie konta
     */
    public function destroyAccount(LlmAccount $account): RedirectResponse
    {
        $id = $account->id;
        $name = $account->name;
        $account->delete();

        AuditLog::record('llm_account.deleted', 'LlmAccount', (string) $id, [
            'name' => $name,
        ]);

        return back()->with('success', __('ai.account_deleted'));
    }

    /**
     * Test połączenia z kontem (AJAX / JSON)
     */
    public function testConnection(LlmAccount $account, LlmConnectionTester $tester): JsonResponse
    {
        $result = $tester->test($account);

        if ($result['success']) {
            $account->markAsActive();
        } else {
            $account->update([
                'current_status' => 'error',
                'last_error_message' => $result['message'],
            ]);
        }

        return response()->json($result);
    }

    public function pools(): View
    {
        return view('module-ai-settings::pools');
    }

    public function limits(): View
    {
        return view('module-ai-settings::limits');
    }
}
