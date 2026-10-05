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
// Spec: https://github.com/ollama/ollama/blob/main/docs/api.md
class OllamaDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'llama3.2');
        $baseUrl = rtrim($account->provider?->base_url ?: 'http://localhost:11434', '/');
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new CompletionResponse(
                content: "Mocked Ollama local response for: " . ($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 15,
                completionTokens: 25,
                totalTokens: 40,
                latencyMs: max(10, $latency),
                ttftMs: 8,
                accountId: $account->id,
            );
        }

        $response = Http::timeout(60)->post($baseUrl . '/api/chat', [
            'model' => $model,
            'messages' => $request->messages,
            'stream' => false,
            'options' => [
                'temperature' => $request->temperature,
                'num_predict' => $request->maxTokens,
            ],
        ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            throw new RateLimitExceededException('Ollama rate limit exceeded', 30, $account->id);
        }

        if (! $response->successful()) {
            throw new LlmDriverException('Ollama API error: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['message']['content'] ?? '';
        $promptTokens = $data['prompt_eval_count'] ?? 0;
        $evalTokens = $data['eval_count'] ?? 0;

        return new CompletionResponse(
            content: $content,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $evalTokens,
            totalTokens: $promptTokens + $evalTokens,
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
        $startTime = microtime(true);
        $model = $request->model ?: 'nomic-embed-text';
        $baseUrl = rtrim($account->provider?->base_url ?: 'http://localhost:11434', '/');
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $mockVec = array_fill(0, $request->dimensions ?: 768, 0.02);
            return new EmbeddingResponse(
                embeddings: is_array($request->input) ? [$mockVec] : $mockVec,
                model: $model,
                totalTokens: 12,
                latencyMs: 10,
                accountId: $account->id,
            );
        }

        $prompt = is_array($request->input) ? implode("\n", $request->input) : (string) $request->input;

        $response = Http::timeout(30)->post($baseUrl . '/api/embeddings', [
            'model' => $model,
            'prompt' => $prompt,
        ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new LlmDriverException('Ollama embedding error: ' . $response->body());
        }

        $data = $response->json();
        $embedding = $data['embedding'] ?? [];

        return new EmbeddingResponse(
            embeddings: is_array($request->input) ? [$embedding] : $embedding,
            model: $model,
            totalTokens: 10,
            latencyMs: $latency,
            accountId: $account->id,
        );
    }

    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $startTime = microtime(true);
        $baseUrl = rtrim($account->provider?->base_url ?: 'http://localhost:11434', '/');
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 5, 'Mock Ollama connection ok');
        }

        try {
            $response = Http::timeout(5)->get($baseUrl . '/api/tags');
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return new ConnectionTestResult(true, $latency, 'Połączenie pomyślne (Ollama lokalny)');
            }

            return new ConnectionTestResult(false, $latency, 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 100));
        } catch (\Exception $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new ConnectionTestResult(false, $latency, $e->getMessage());
        }
    }
}
