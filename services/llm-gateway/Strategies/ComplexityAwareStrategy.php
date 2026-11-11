<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Strategies;

use App\Contracts\Llm\CompletionRequest;
use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use App\Services\LlmGateway\PromptComplexityAnalyzer;
use Illuminate\Support\Collection;

class ComplexityAwareStrategy implements RoutingStrategyInterface
{
    public function __construct(
        protected PromptComplexityAnalyzer $analyzer = new PromptComplexityAnalyzer,
    ) {}

    public function selectAccount(Collection $availableAccounts, LlmAccountPool $pool, ?CompletionRequest $request = null): ?LlmAccount
    {
        if ($availableAccounts->isEmpty()) {
            return null;
        }

        if ($request === null) {
            return (new WeightedStrategy)->selectAccount($availableAccounts, $pool);
        }

        $analysis = $this->analyzer->analyze($request);
        $tier = $analysis['tier'];

        // 1. Niska złożoność (low): preferuj darmowe konta lokalne (Ollama / LM Studio) lub lekkie modele Flash/Mini
        if ($tier === 'low') {
            $localOrFastAccount = $availableAccounts->first(function (LlmAccount $account): bool {
                $driver = strtolower((string) ($account->provider?->driver ?? ''));
                $defaultModel = strtolower((string) ($account->provider?->default_model ?? ''));

                return in_array($driver, ['ollama', 'lmstudio'], true)
                    || str_contains($defaultModel, 'flash')
                    || str_contains($defaultModel, 'mini')
                    || str_contains($defaultModel, 'haiku');
            });

            if ($localOrFastAccount !== null) {
                return $localOrFastAccount;
            }

            // Jeśli brak lokalnych/Flash, wybierz konto o najniższej wadze kosztowej lub najniższym priorytecie (oszczędność kont flagowych)
            return $availableAccounts->sortBy(function (LlmAccount $account): int {
                return (int) ($account->pivot?->custom_weight ?? $account->weight ?? 1);
            })->first();
        }

        // 2. Wysoka złożoność (high): preferuj konta chmurowe obsługujące modele flagowe (o najwyższej wadze / najwyższym priorytecie 1)
        if ($tier === 'high') {
            $flagshipAccounts = $availableAccounts->filter(function (LlmAccount $account): bool {
                $driver = strtolower((string) ($account->provider?->driver ?? ''));

                return in_array($driver, ['anthropic', 'openai', 'gemini', 'openrouter'], true);
            });

            $candidates = $flagshipAccounts->isNotEmpty() ? $flagshipAccounts : $availableAccounts;

            return $candidates->sortByDesc(function (LlmAccount $account): int {
                $weight = (int) ($account->pivot?->custom_weight ?? $account->weight ?? 10);
                $priorityBonus = max(0, 10 - (int) ($account->pivot?->priority ?? 5)) * 10;

                return $weight + $priorityBonus;
            })->first();
        }

        // 3. Średnia złożoność (medium): zbalansowany wybór ważony
        return (new WeightedStrategy)->selectAccount($availableAccounts, $pool, $request);
    }
}

