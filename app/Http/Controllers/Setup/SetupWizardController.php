<?php

declare(strict_types=1);

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\IntegrationManager\IntegrationService;
use App\Services\MemoryService\QdrantVectorStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SetupWizardController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function show(int $step = 1): View|RedirectResponse
    {
        if ($step < 1 || $step > 7) {
            return redirect()->route('setup.step', ['step' => 1]);
        }

        $providers = LlmProvider::where('is_active', true)->get();
        $detections = [
            'hermes' => $this->integrationService->getAdapter('hermes')->detect(),
            'openclaw' => $this->integrationService->getAdapter('openclaw')->detect(),
            'claude_code' => $this->integrationService->getAdapter('claude_code')->detect(),
            'codex' => $this->integrationService->getAdapter('codex')->detect(),
        ];

        return view('setup.wizard', [
            'step' => $step,
            'totalSteps' => 7,
            'providers' => $providers,
            'detections' => $detections,
        ]);
    }

    public function save(Request $request, int $step): RedirectResponse
    {
        $user = auth()->user();

        switch ($step) {
            case 1:
                $locale = $request->input('locale', 'pl');
                if (in_array($locale, ['pl', 'en'], true)) {
                    session(['locale' => $locale]);
                    app()->setLocale($locale);
                }
                break;

            case 2:
                if ($request->filled('password')) {
                    $request->validate([
                        'password' => ['required', 'string', 'min:5', 'confirmed'],
                        'email' => ['required', 'email'],
                    ]);

                    if ($user) {
                        $user->update([
                            'email' => $request->input('email'),
                            'password' => Hash::make($request->input('password')),
                        ]);
                    }
                }
                break;

            case 3:
                $driver = $request->input('vector_driver', 'qdrant');
                session(['setup_vector_driver' => $driver]);
                break;

            case 4:
                if ($request->filled('api_key') && $request->filled('provider_id')) {
                    $request->validate([
                        'provider_id' => ['required', 'exists:llm_providers,id'],
                        'account_name' => ['required', 'string', 'max:100'],
                        'api_key' => ['required', 'string'],
                    ]);

                    LlmAccount::create([
                        'provider_id' => (int) $request->input('provider_id'),
                        'name' => $request->input('account_name'),
                        'api_key' => $request->input('api_key'),
                        'weight' => 10,
                        'current_status' => 'active',
                    ]);
                }
                break;

            case 5:
                // Integracje - informacyjne / auto-detekcja
                break;

            case 6:
                if ($request->filled('agent_name')) {
                    $request->validate([
                        'agent_name' => ['required', 'string', 'max:100'],
                    ]);

                    Agent::create([
                        'name' => $request->input('agent_name'),
                        'slug' => Str::slug($request->input('agent_name')),
                        'description' => 'Główny asystent utworzony w Setup Wizardzie',
                        'runtime' => 'native',
                        'system_prompt' => 'Jesteś pomocnym asystentem AgentHub.',
                        'temperature' => 0.7,
                        'is_active' => true,
                    ]);
                }
                break;
        }

        if ($step >= 7) {
            return $this->finish();
        }

        return redirect()->route('setup.step', ['step' => $step + 1]);
    }

    public function skip(int $step): RedirectResponse
    {
        if ($step >= 7) {
            return $this->finish();
        }

        return redirect()->route('setup.step', ['step' => $step + 1]);
    }

    public function finish(): RedirectResponse
    {
        // Oznaczenie flagi w pliku .env i cache
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            if (str_contains($content, 'SETUP_COMPLETED=')) {
                $content = preg_replace('/^SETUP_COMPLETED=.*/m', 'SETUP_COMPLETED=true', $content);
            } else {
                $content .= "\nSETUP_COMPLETED=true\n";
            }
            file_put_contents($envPath, $content);
        }

        Cache::forever('agenthub_setup_completed', true);
        config(['agenthub.setup_completed' => true]);

        return redirect()->route('dashboard.index')->with('success', __('setup.completed_success'));
    }
}
