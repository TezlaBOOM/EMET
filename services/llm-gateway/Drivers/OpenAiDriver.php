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
// Spec: https://platform.openai.com/docs/api-reference/chat
class OpenAiDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'gpt-4o');
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://api.openai.com/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new CompletionResponse(
                content: "Mocked OpenAI response for: " . ($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 25,
                completionTokens: 15,
                totalTokens: 40,
                latencyMs: max(10, $latency),
                ttftMs: 5,
                accountId: $account->id,
            );
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($account->organization_id) {
            $headers['OpenAI-Organization'] = $account->organization_id;
        }

        $response = Http::withHeaders($headers)
            ->timeout(60)
            ->post($baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => $request->messages,
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
            ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new RateLimitExceededException(
                message: 'OpenAI rate limit exceeded: ' . $response->body(),
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            throw new LlmDriverException('OpenAI API error (' . $response->status() . '): ' . $response->body());
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
            usleep(20000); // 20ms simulation
        }
    }

    public function embed(EmbeddingRequest $request, LlmAccount $account): EmbeddingResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: 'text-embedding-3-small';
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://api.openai.com/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $dim = $request->dimensions;
            $mockVec = array_fill(0, $dim, 0.05);
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            return new EmbeddingResponse(
                embeddings: is_array($request->input) ? [$mockVec] : $mockVec,
                model: $model,
                totalTokens: 10,
                latencyMs: max(5, $latency),
                accountId: $account->id,
            );
        }

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post($baseUrl . '/embeddings', [
                'model' => $model,
                'input' => $request->input,
                'dimensions' => $request->dimensions,
            ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new RateLimitExceededException(
                message: 'OpenAI embedding rate limit exceeded',
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            throw new LlmDriverException('OpenAI embedding error: ' . $response->body());
        }

        $data = $response->json();
        $embeddings = array_map(fn ($item) => $item['embedding'], $data['data'] ?? []);

        return new EmbeddingResponse(
            embeddings: is_array($request->input) ? $embeddings : ($embeddings[0] ?? []),
            model: $model,
            totalTokens: $data['usage']['total_tokens'] ?? 0,
            latencyMs: $latency,
            accountId: $account->id,
        );
    }

    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $startTime = microtime(true);
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://api.openai.com/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 15, 'Mock OpenAI connection ok');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get($baseUrl . '/models');

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
