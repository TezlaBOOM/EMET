<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Drivers;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\CompletionResponse;
use App\Contracts\Llm\ConnectionTestResult;
use App\Contracts\Llm\EmbeddingRequest;
use App\Contracts\Llm\EmbeddingResponse;
use App\Contracts\Llm\LlmDriverInterface;
use App\Models\LlmAccount;
use App\Services\LlmGateway\Exceptions\LlmDriverException;
use App\Services\LlmGateway\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Http;

// TODO: SDK Pending - using mocked driver / HTTP client
// Spec: https://openrouter.ai/docs
class OpenRouterDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'auto');
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://openrouter.ai/api/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new CompletionResponse(
                content: "Mocked OpenRouter response for: " . ($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 22,
                completionTokens: 35,
                totalTokens: 57,
                latencyMs: max(10, $latency),
                ttftMs: 6,
                accountId: $account->id,
            );
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'HTTP-Referer' => config('app.url', 'http://localhost'),
            'X-Title' => 'Projekt-Emet Gateway',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($baseUrl . '/chat/completions', [
            'model' => $model,
            'messages' => $request->messages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new RateLimitExceededException(
                message: 'OpenRouter rate limit exceeded',
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            throw new LlmDriverException('OpenRouter API error (' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return new CompletionResponse(
            content: $content,
            model: $data['model'] ?? $model,
            promptTokens: $usage['prompt_tokens'] ?? 0,
            completionTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: $usage['total_tokens'] ?? 0,
            latencyMs: $latency,
            ttftMs: null,
            accountId: $account->id,
            raw: $data,
        );
    }

    public function stream(CompletionRequest $request, LlmAccount $account): iterable
    {
        $response = $this->complete($request, $account);
        $words = explode(' ', $response->content);

        foreach ($words as $i => $word) {
            yield ($i > 0 ? ' ' : '') . $word;
            usleep(20000);
        }
    }

    public function embed(EmbeddingRequest $request, LlmAccount $account): EmbeddingResponse
    {
        $dim = $request->dimensions;
        $mockVec = array_fill(0, $dim, 0.04);
        return new EmbeddingResponse(
            embeddings: is_array($request->input) ? [$mockVec] : $mockVec,
            model: $request->model ?: 'openrouter-embedding-mock',
            totalTokens: 10,
            latencyMs: 10,
            accountId: $account->id,
        );
    }

    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $startTime = microtime(true);
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://openrouter.ai/api/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 18, 'Mock OpenRouter connection ok');
        }

        try {
            $response = Http::withToken($apiKey)->timeout(10)->get($baseUrl . '/models');
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return new ConnectionTestResult(true, $latency, 'Połączenie pomyślne');
            }

            return new ConnectionTestResult(false, $latency, 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 100));
        } catch (\Exception $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new ConnectionTestResult(false, $latency, $e->getMessage());
        }
    }
}
