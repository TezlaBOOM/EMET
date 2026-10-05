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
// Spec: https://docs.anthropic.com/en/api/messages
class AnthropicDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'claude-3-5-sonnet-20241022');
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://api.anthropic.com/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new CompletionResponse(
                content: "Mocked Claude response for: " . ($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 30,
                completionTokens: 40,
                totalTokens: 70,
                latencyMs: max(10, $latency),
                ttftMs: 7,
                accountId: $account->id,
            );
        }

        // Filtrowanie ewentualnego system promptu
        $systemPrompt = null;
        $messages = [];
        foreach ($request->messages as $m) {
            if ($m['role'] === 'system') {
                $systemPrompt = $m['content'];
            } else {
                $messages[] = [
                    'role' => $m['role'],
                    'content' => $m['content'],
                ];
            }
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ];
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post($baseUrl . '/messages', $payload);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new RateLimitExceededException(
                message: 'Anthropic rate limit exceeded: ' . $response->body(),
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            throw new LlmDriverException('Anthropic API error (' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['input_tokens'] ?? 0;
        $completionTokens = $usage['output_tokens'] ?? 0;

        return new CompletionResponse(
            content: $text,
            model: $data['model'] ?? $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
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
        // Anthropic nie oferuje natywnych embeddingów bezpośrednio w API wiadomości;
        // Zwracamy wektor zastępczy (mock) lub rzucamy wyjątek o braku obsługi embeddingów
        $mockVec = array_fill(0, $request->dimensions, 0.01);
        return new EmbeddingResponse(
            embeddings: is_array($request->input) ? [$mockVec] : $mockVec,
            model: 'claude-embedding-fallback',
            totalTokens: 5,
            latencyMs: 10,
            accountId: $account->id,
        );
    }

    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $startTime = microtime(true);
        $apiKey = $account->api_key;
        $baseUrl = rtrim($account->provider?->base_url ?: 'https://api.anthropic.com/v1', '/');

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 14, 'Mock Anthropic connection ok');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->get($baseUrl . '/models');

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
