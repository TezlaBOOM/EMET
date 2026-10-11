<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiSettings;

use App\Contracts\Llm\ModelManagementInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LlmAccount;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\ModelPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModelsController extends Controller
{
    public function __construct(
        protected ModelManagementInterface $modelManagementService
    ) {}

    /**
     * Lista zarejestrowanych modeli LLM
     */
    public function index(Request $request): View
    {
        $query = LlmModel::with('provider');

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->query('provider_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('model_key', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $models = $query->orderBy('sort')->orderBy('model_key')->paginate(25);
        $providers = LlmProvider::all();
        $accounts = LlmAccount::all();
        $pricing = ModelPricing::all()->keyBy('model_pattern');

        return view('module-ai-settings::models', compact('models', 'providers', 'accounts', 'pricing'));
    }

    /**
     * Ręczne dodanie nowego modelu
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:llm_providers,id'],
            'model_key' => ['required', 'string', 'max:150'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'context_window' => ['required', 'integer', 'min:512'],
            'max_output_tokens' => ['nullable', 'integer', 'min:128'],
            'capabilities' => ['nullable', 'array'],
            'input_cost_per_million' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_million' => ['nullable', 'numeric', 'min:0'],
        ]);

        $model = LlmModel::create([
            'provider_id' => $validated['provider_id'],
            'model_key' => $validated['model_key'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'context_window' => $validated['context_window'],
            'max_output_tokens' => $validated['max_output_tokens'] ?? null,
            'capabilities' => $validated['capabilities'] ?? ['chat'],
            'enabled' => true,
            'is_manual' => true,
            'overrides' => [],
        ]);

        if (isset($validated['input_cost_per_million']) || isset($validated['output_cost_per_million'])) {
            ModelPricing::updateOrCreate(
                ['model_pattern' => $model->model_key, 'account_id' => null],
                [
                    'input_cost_per_million' => (float) ($validated['input_cost_per_million'] ?? 0),
                    'output_cost_per_million' => (float) ($validated['output_cost_per_million'] ?? 0),
                ]
            );
        }

        AuditLog::record('model.create', LlmModel::class, (string) $model->id, ['model_key' => $model->model_key]);

        return redirect()->route('ai-settings.models.index')->with('success', __('ai.model_saved'));
    }

    /**
     * Edycja parametrów modelu i cennika
     */
    public function update(Request $request, LlmModel $model): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'context_window' => ['required', 'integer', 'min:512'],
            'max_output_tokens' => ['nullable', 'integer', 'min:128'],
            'enabled' => ['nullable', 'boolean'],
            'capabilities' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'input_cost_per_million' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_million' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updateAttributes = [
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'context_window' => $validated['context_window'],
            'max_output_tokens' => $validated['max_output_tokens'] ?? null,
            'enabled' => $request->boolean('enabled'),
            'capabilities' => $validated['capabilities'] ?? ['chat'],
            'parameters' => $validated['parameters'] ?? null,
        ];

        $this->modelManagementService->updateModelWithOverrides($model, $updateAttributes);

        if (isset($validated['input_cost_per_million']) || isset($validated['output_cost_per_million'])) {
            ModelPricing::updateOrCreate(
                ['model_pattern' => $model->model_key, 'account_id' => null],
                [
                    'input_cost_per_million' => (float) ($validated['input_cost_per_million'] ?? 0),
                    'output_cost_per_million' => (float) ($validated['output_cost_per_million'] ?? 0),
                ]
            );
        }

        AuditLog::record('model.update', LlmModel::class, (string) $model->id, ['model_key' => $model->model_key]);

        return redirect()->route('ai-settings.models.index')->with('success', __('ai.model_saved'));
    }

    /**
     * Reset nadpisań modelu do wartości domyślnych dostawcy
     */
    public function reset(LlmModel $model): RedirectResponse
    {
        $this->modelManagementService->resetOverridesToProviderDefaults($model);
        AuditLog::record('model.reset', LlmModel::class, (string) $model->id, ['model_key' => $model->model_key]);

        return redirect()->route('ai-settings.models.index')->with('success', __('ai.model_reset_success'));
    }

    /**
     * Usunięcie modelu z rejestru
     */
    public function destroy(LlmModel $model): RedirectResponse
    {
        $deps = $this->modelManagementService->getModelDependencies($model);
        if (! empty($deps['agents']) || ! empty($deps['scenarios'])) {
            $msg = 'Nie można usunąć modelu, ponieważ jest używany przez: '
                .implode(', ', array_merge($deps['agents'], $deps['scenarios']));

            return redirect()->route('ai-settings.models.index')->with('error', $msg);
        }

        AuditLog::record('model.delete', LlmModel::class, (string) $model->id, ['model_key' => $model->model_key]);
        $model->delete();

        return redirect()->route('ai-settings.models.index')->with('success', __('ai.model_deleted'));
    }

    /**
     * Synchronizacja modeli z dostawcą
     */
    public function sync(LlmProvider $provider): RedirectResponse
    {
        // Domyślne profile modeli dla znanych dostawców
        $defaultModels = match ($provider->driver) {
            'anthropic' => [
                ['model_key' => 'claude-3-5-sonnet-20241022', 'display_name' => 'Claude 3.5 Sonnet', 'context_window' => 200000, 'capabilities' => ['chat', 'vision', 'tools']],
                ['model_key' => 'claude-3-5-haiku-20241022', 'display_name' => 'Claude 3.5 Haiku', 'context_window' => 200000, 'capabilities' => ['chat', 'tools']],
                ['model_key' => 'claude-3-opus-20240229', 'display_name' => 'Claude 3 Opus', 'context_window' => 200000, 'capabilities' => ['chat', 'vision', 'tools']],
            ],
            'openai' => [
                ['model_key' => 'gpt-4o', 'display_name' => 'GPT-4o', 'context_window' => 128000, 'capabilities' => ['chat', 'vision', 'tools']],
                ['model_key' => 'gpt-4o-mini', 'display_name' => 'GPT-4o Mini', 'context_window' => 128000, 'capabilities' => ['chat', 'vision', 'tools']],
                ['model_key' => 'o1', 'display_name' => 'o1', 'context_window' => 200000, 'capabilities' => ['chat', 'tools']],
                ['model_key' => 'o3-mini', 'display_name' => 'o3 Mini', 'context_window' => 200000, 'capabilities' => ['chat', 'tools']],
            ],
            'gemini' => [
                ['model_key' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro', 'context_window' => 1000000, 'capabilities' => ['chat', 'vision', 'tools']],
                ['model_key' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash', 'context_window' => 1000000, 'capabilities' => ['chat', 'vision', 'tools']],
                ['model_key' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash', 'context_window' => 1000000, 'capabilities' => ['chat', 'vision', 'tools']],
            ],
            'lmstudio', 'ollama' => [
                ['model_key' => $provider->default_model ?? 'local-model', 'display_name' => $provider->default_model ?? 'Local Model', 'context_window' => 8192, 'capabilities' => ['chat', 'tools']],
            ],
            default => [
                ['model_key' => $provider->default_model ?? 'default-model', 'display_name' => $provider->default_model ?? 'Default Model', 'context_window' => 4096, 'capabilities' => ['chat']],
            ],
        };

        $result = $this->modelManagementService->syncProviderModels($provider, $defaultModels);

        AuditLog::record('model.sync', LlmProvider::class, (string) $provider->id, $result);

        $msg = __('ai.sync_success', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'preserved' => $result['preserved_overrides'],
        ]);

        return redirect()->route('ai-settings.models.index')->with('success', $msg);
    }
}
