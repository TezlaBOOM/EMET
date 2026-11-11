<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\AiGuardrailsInterface;
use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\CompletionResponse;
use App\Contracts\Llm\ConnectionTestResult;
use App\Contracts\Llm\EmbeddingRequest;
use App\Contracts\Llm\EmbeddingResponse;
use App\Contracts\Llm\LlmDriverInterface;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Llm\SemanticCacheInterface;
use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use App\Models\LlmCall;
use App\Models\ModelPricing;
use App\Services\LlmGateway\Drivers\AnthropicDriver;
use App\Services\LlmGateway\Drivers\GeminiDriver;
use App\Services\LlmGateway\Drivers\LmStudioDriver;
use App\Services\LlmGateway\Drivers\OllamaDriver;
use App\Services\LlmGateway\Drivers\OpenAiDriver;
use App\Services\LlmGateway\Drivers\OpenRouterDriver;
use App\Services\LlmGateway\Exceptions\GuardrailViolationException;
use App\Services\LlmGateway\Exceptions\LlmDriverException;
use App\Services\LlmGateway\Exceptions\RateLimitExceededException;
use App\Services\LlmGateway\Strategies\ComplexityAwareStrategy;
use App\Services\LlmGateway\Strategies\LeastUsedStrategy;
use App\Services\LlmGateway\Strategies\PriorityFallbackStrategy;
use App\Services\LlmGateway\Strategies\RoundRobinStrategy;
use App\Services\LlmGateway\Strategies\RoutingStrategyInterface;
use App\Services\LlmGateway\Strategies\WeightedStrategy;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LlmGateway implements LlmGatewayInterface
{
    /**
     * @var array<string, LlmDriverInterface>
     */
    protected array $drivers = [];

    /**
     * @var array<string, RoutingStrategyInterface>
     */
    protected array $strategies = [];

    protected SemanticCacheInterface $semanticCache;

    protected AiGuardrailsInterface $guardrails;

    public function __construct(
        ?SemanticCacheInterface $semanticCache = null,
        ?AiGuardrailsInterface $guardrails = null,
    ) {
        $this->semanticCache = $semanticCache ?? new SemanticCacheService;
        $this->guardrails = $guardrails ?? new AiGuardrailsService;

        $this->drivers = [
            'gemini' => new GeminiDriver,
            'openai' => new OpenAiDriver,
            'anthropic' => new AnthropicDriver,
            'ollama' => new OllamaDriver,
            'openrouter' => new OpenRouterDriver,
            'lmstudio' => new LmStudioDriver,
        ];

        $this->strategies = [
            'round_robin' => new RoundRobinStrategy,
            'weighted' => new WeightedStrategy,
            'least_used' => new LeastUsedStrategy,
            'priority_fallback' => new PriorityFallbackStrategy,
            'complexity_aware' => new ComplexityAwareStrategy,
        ];
    }

    public function getDriver(string $driverName): LlmDriverInterface
    {
        $driverKey = strtolower($driverName);

        if (! isset($this->drivers[$driverKey])) {
            throw new LlmDriverException("Nieobsługiwany sterownik LLM: [{$driverName}]");
        }

        return $this->drivers[$driverKey];
    }

    public function getStrategy(string $strategyName): RoutingStrategyInterface
    {
        $key = strtolower($strategyName);

        return $this->strategies[$key] ?? $this->strategies['weighted'];
    }

    public function getSemanticCache(): SemanticCacheInterface
    {
        return $this->semanticCache;
    }

    public function getGuardrails(): AiGuardrailsInterface
    {
        return $this->guardrails;
    }

    /**
     * Synchroniczne wywołanie modelu z automatycznym doborem konta z puli i failoverem przy 429
     */
    public function complete(CompletionRequest $request, ?LlmAccountPool $pool = null, ?LlmAccount $specificAccount = null): CompletionResponse
    {
        $startTime = microtime(true);
        $attempts = 0;
        $maxAttempts = 3;
        $excludedAccountIds = [];

        // 1. Jeśli przekazano konkretne konto, używamy go bezpośrednio
        if ($specificAccount !== null) {
            return $this->executeWithAccount($specificAccount, $request, $startTime);
        }

        // 2. Jeśli nie podano puli, dobieramy konto pasujące do modelu lub aktywne konto fallback
        if ($pool === null) {
            $fallbackAccount = null;

            if (! empty($request->model)) {
                $modelLower = strtolower($request->model);
                $driverGuess = match (true) {
                    str_contains($modelLower, 'gemini') => 'gemini',
                    str_contains($modelLower, 'gpt') || str_contains($modelLower, 'o1') || str_contains($modelLower, 'o3') => 'openai',
                    str_contains($modelLower, 'claude') => 'anthropic',
                    str_contains($modelLower, 'lmstudio') || str_contains($modelLower, 'lm-studio') => 'lmstudio',
                    str_contains($modelLower, 'llama') || str_contains($modelLower, 'mistral') || str_contains($modelLower, 'qwen') || str_contains($modelLower, 'deepseek') => 'ollama',
                    default => null,
                };

                if ($driverGuess !== null) {
                    $fallbackAccount = LlmAccount::with('provider')
                        ->whereHas('provider', fn ($q) => $q->where('driver', $driverGuess))
                        ->where('current_status', '!=', 'error')
                        ->latest()
                        ->first();
                }
            }

            if (! $fallbackAccount) {
                $fallbackAccount = LlmAccount::with('provider')
                    ->where('current_status', '!=', 'error')
                    ->latest()
                    ->first();
            }

            if (! $fallbackAccount) {
                throw new LlmDriverException('Brak skonfigurowanych kont LLM w systemie. Przejdź do panelu Ustawienia AI -> Konta i dodaj klucz API dostawcy.');
            }

            return $this->executeWithAccount($fallbackAccount, $request, $startTime);
        }

        // 3. Pętla failoveru w ramach wybranej puli kont
        $accountsInPool = $pool->accounts()->with('provider')->get();
        $maxAttempts = min($maxAttempts, max(1, $accountsInPool->count()));

        while ($attempts < $maxAttempts) {
            $attempts++;

            // Pobieramy dostępne konta, filtrując cooldown i wykluczone w bieżącym przebiegu
            /** @var Collection<int, LlmAccount> $availableAccounts */
            $availableAccounts = $accountsInPool
                ->filter(fn (LlmAccount $acc) => ! in_array($acc->id, $excludedAccountIds, true))
                ->filter(fn (LlmAccount $acc) => $acc->isAvailable());

            if ($availableAccounts->isEmpty()) {
                throw new LlmDriverException(
                    "Wszystkie konta w puli [{$pool->name}] są obecnie niedostępne lub w stanie cooldown."
                );
            }

            $strategy = $this->getStrategy($pool->strategy);
            $selectedAccount = $strategy->selectAccount($availableAccounts, $pool, $request);

            if (! $selectedAccount) {
                throw new LlmDriverException("Strategia nie mogła wybrać konta z puli [{$pool->name}]");
            }

            try {
                $response = $this->executeWithAccount($selectedAccount, $request, $startTime);

                // Oznaczenie ostatniego użycia
                $selectedAccount->update(['last_used_at' => now()]);

                return $response;
            } catch (GuardrailViolationException $e) {
                // Błąd naruszenia polityk bezpieczeństwa (Jailbreak) nie podlega failoverowi na inne konto
                $this->recordTelemetry(
                    request: $request,
                    account: $selectedAccount,
                    model: $request->model ?: ($selectedAccount->provider?->default_model ?: 'unknown'),
                    promptTokens: 0,
                    completionTokens: 0,
                    durationMs: (int) round((microtime(true) - $startTime) * 1000),
                    status: 'blocked_guardrail',
                    errorMessage: $e->getMessage(),
                );

                throw $e;
            } catch (RateLimitExceededException $e) {
                // Wykryto 429: automatyczny cooldown i failover
                $selectedAccount->setCooldown($e->retryAfterSeconds, $e->getMessage());
                $excludedAccountIds[] = $selectedAccount->id;

                // Logowanie zdarzenia telemetrycznego błędu 429
                $this->recordTelemetry(
                    request: $request,
                    account: $selectedAccount,
                    model: $request->model ?: ($selectedAccount->provider?->default_model ?: 'unknown'),
                    promptTokens: 0,
                    completionTokens: 0,
                    durationMs: (int) round((microtime(true) - $startTime) * 1000),
                    status: 'rate_limited',
                    errorMessage: $e->getMessage(),
                );

                // Kontynuacja w pętli failoveru
                continue;
            } catch (Exception $e) {
                $excludedAccountIds[] = $selectedAccount->id;

                $this->recordTelemetry(
                    request: $request,
                    account: $selectedAccount,
                    model: $request->model ?: ($selectedAccount->provider?->default_model ?: 'unknown'),
                    promptTokens: 0,
                    completionTokens: 0,
                    durationMs: (int) round((microtime(true) - $startTime) * 1000),
                    status: 'failed',
                    errorMessage: $e->getMessage(),
                );

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new LlmDriverException("Nie udało się zrealizować zapytania po {$attempts} próbach failoveru.");
    }

    /**
     * Strumieniowanie tokenów (generator)
     */
    public function stream(CompletionRequest $request, ?LlmAccountPool $pool = null, ?LlmAccount $specificAccount = null): iterable
    {
        $startTime = microtime(true);
        $account = $specificAccount;

        if (! $account && $pool) {
            $available = $pool->accounts()->with('provider')->get()->filter(fn (LlmAccount $a) => $a->isAvailable());
            $account = $this->getStrategy($pool->strategy)->selectAccount($available, $pool, $request);
        }

        if (! $account) {
            if (! empty($request->model)) {
                $modelLower = strtolower($request->model);
                $driverGuess = match (true) {
                    str_contains($modelLower, 'gemini') => 'gemini',
                    str_contains($modelLower, 'gpt') || str_contains($modelLower, 'o1') || str_contains($modelLower, 'o3') => 'openai',
                    str_contains($modelLower, 'claude') => 'anthropic',
                    str_contains($modelLower, 'lmstudio') || str_contains($modelLower, 'lm-studio') => 'lmstudio',
                    str_contains($modelLower, 'llama') || str_contains($modelLower, 'mistral') || str_contains($modelLower, 'qwen') || str_contains($modelLower, 'deepseek') => 'ollama',
                    default => null,
                };

                if ($driverGuess !== null) {
                    $account = LlmAccount::with('provider')
                        ->whereHas('provider', fn ($q) => $q->where('driver', $driverGuess))
                        ->where('current_status', 'active')
                        ->first();
                }
            }

            if (! $account) {
                $account = LlmAccount::with('provider')->where('current_status', 'active')->first();
            }
        }

        if (! $account) {
            throw new LlmDriverException('Brak dostępnego konta do strumieniowania. Przejdź do panelu Ustawienia AI -> Konta i dodaj lub aktywuj konto.');
        }

        $driverName = strtolower($account->provider?->driver ?? 'openai');
        $isLocalModel = in_array($driverName, ['ollama', 'lmstudio'], true);

        // Inspekcja AI Guardrails & DLP przed strumieniowaniem
        $guardrailResult = $this->guardrails->inspectAndMaskRequest($request, $isLocalModel);
        $effectiveRequest = $guardrailResult['request'];
        $replacements = $guardrailResult['replacements'];

        $driver = $this->getDriver($driverName);
        $fullContent = '';
        $tokenCount = 0;
        $ttft = null;

        foreach ($driver->stream($effectiveRequest, $account) as $chunk) {
            if ($ttft === null) {
                $ttft = (int) round((microtime(true) - $startTime) * 1000);
            }
            if (! empty($replacements)) {
                $chunk = strtr($chunk, $replacements);
            }
            $fullContent .= $chunk;
            $tokenCount++;
            yield $chunk;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Zapis telemetrii po zakończeniu strumienia
        $this->recordTelemetry(
            request: $effectiveRequest,
            account: $account,
            model: $effectiveRequest->model ?: ($account->provider?->default_model ?: 'unknown'),
            promptTokens: count($effectiveRequest->messages) * 10,
            completionTokens: $tokenCount,
            durationMs: $durationMs,
            ttftMs: $ttft,
            status: 'success',
            responseContent: $fullContent,
        );
    }

    /**
     * Generowanie embeddingów
     */
    public function embed(EmbeddingRequest $request, ?LlmAccount $account = null): EmbeddingResponse
    {
        $targetAccount = $account ?: LlmAccount::with('provider')->where('current_status', 'active')->first();

        if (! $targetAccount) {
            throw new LlmDriverException('Brak dostępnego konta dla generowania embeddingów');
        }

        $driver = $this->getDriver($targetAccount->provider?->driver ?? 'openai');

        return $driver->embed($request, $targetAccount);
    }

    /**
     * Test łączności z kontem
     */
    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $driver = $this->getDriver($account->provider?->driver ?? 'openai');

        return $driver->testConnection($account);
    }

    /**
     * Wykonuje zapytanie za pomocą wskazanego konta z obsługą AI Guardrails, DLP oraz Semantic Cache
     */
    protected function executeWithAccount(LlmAccount $account, CompletionRequest $request, float $startTime): CompletionResponse
    {
        $driverName = strtolower($account->provider?->driver ?? 'openai');
        $isLocalModel = in_array($driverName, ['ollama', 'lmstudio'], true);

        // 1. Inspekcja bezpieczeństwa AI Guardrails i pseudonimizacja DLP
        $guardrailResult = $this->guardrails->inspectAndMaskRequest($request, $isLocalModel);
        $effectiveRequest = $guardrailResult['request'];
        $replacements = $guardrailResult['replacements'];

        // 2. Sprawdzenie Semantycznej Pamięci Podręcznej (Semantic Cache)
        $cachedResponse = $this->semanticCache->lookup($effectiveRequest);
        if ($cachedResponse !== null) {
            $cachedResponse->accountId = $account->id;
            $restoredCached = $this->guardrails->inspectAndRestoreResponse($cachedResponse, $replacements);
            $durationMs = max(1, (int) round((microtime(true) - $startTime) * 1000));

            $this->recordTelemetry(
                request: $effectiveRequest,
                account: $account,
                model: $restoredCached->model,
                promptTokens: $restoredCached->promptTokens,
                completionTokens: $restoredCached->completionTokens,
                durationMs: $durationMs,
                ttftMs: $restoredCached->ttftMs ?? $durationMs,
                status: 'cache_hit',
                responseContent: $restoredCached->content,
                estimatedCost: 0.0,
            );

            return $restoredCached;
        }

        // 3. Wywołanie sterownika dostawcy LLM
        $driver = $this->getDriver($driverName);
        $response = $driver->complete($effectiveRequest, $account);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $cost = ModelPricing::calculateCost($response->model, $response->promptTokens, $response->completionTokens);
        $response->estimatedCost = $cost;

        // 4. Zapis odpowiedzi w Semantic Cache (w formie zmaskowanej przez DLP)
        $this->semanticCache->store($effectiveRequest, $response);

        // 5. Odtworzenie oryginalnych danych (de-pseudonimizacja DLP) dla odbiorcy końcowego
        $response = $this->guardrails->inspectAndRestoreResponse($response, $replacements);

        $this->recordTelemetry(
            request: $effectiveRequest,
            account: $account,
            model: $response->model,
            promptTokens: $response->promptTokens,
            completionTokens: $response->completionTokens,
            durationMs: $durationMs,
            ttftMs: $response->ttftMs,
            status: 'success',
            responseContent: $response->content,
            estimatedCost: $cost,
        );

        return $response;
    }

    /**
     * Zapis rekordu do tabeli llm_calls
     */
    protected function recordTelemetry(
        CompletionRequest $request,
        LlmAccount $account,
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $durationMs,
        ?int $ttftMs = null,
        string $status = 'success',
        ?string $responseContent = null,
        ?string $errorMessage = null,
        float $estimatedCost = 0.0,
    ): void {
        $lastMessage = end($request->messages);
        $promptPreview = is_array($lastMessage) ? ($lastMessage['content'] ?? '') : '';

        LlmCall::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $request->metadata['agent_id'] ?? null,
            'account_id' => $account->id,
            'provider_id' => $account->provider_id,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'ttft_ms' => $ttftMs,
            'duration_ms' => $durationMs,
            'estimated_cost_usd' => $estimatedCost,
            'status' => $status,
            'prompt_preview' => mb_substr($promptPreview, 0, 500),
            'response_preview' => $responseContent ? mb_substr($responseContent, 0, 500) : null,
            'error_message' => $errorMessage,
            'created_at' => now(),
        ]);
    }
}
