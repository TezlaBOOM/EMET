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
use Exception;
use Illuminate\Support\Facades\Http;

class LmStudioDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'local-model');
        $apiKey = $account->api_key ?: 'lm-studio';
        $baseUrl = $this->getBaseUrl($account);

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            return new CompletionResponse(
                content: 'Mocked LM Studio response for: '.($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 20,
                completionTokens: 25,
                totalTokens: 45,
                latencyMs: max(10, $latency),
                ttftMs: 5,
                accountId: $account->id,
            );
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ($apiKey && $apiKey !== 'lm-studio') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $payload = [
            'model' => $model,
            'messages' => $request->messages,
            'temperature' => $request->temperature,
        ];
        if ($request->maxTokens) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        $timeout = (int) config('agenthub.llm.request_timeout_seconds', 120);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (Exception $e) {
            throw new LlmDriverException("Błąd połączenia z LM Studio pod adresem [{$baseUrl}]: ".$e->getMessage());
        }

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 30);
            throw new RateLimitExceededException(
                message: 'LM Studio rate limit exceeded',
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            $errorJson = $response->json();
            $errorMsg = $errorJson['error']['message'] ?? $response->body();
            throw new LlmDriverException('LM Studio API error ('.$response->status().'): '.$errorMsg);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;

        return new CompletionResponse(
            content: $content,
            model: $data['model'] ?? $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $usage['total_tokens'] ?? ($promptTokens + $completionTokens),
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
            yield ($i > 0 ? ' ' : '').$word;
            usleep(20000);
        }
    }

    public function embed(EmbeddingRequest $request, LlmAccount $account): EmbeddingResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: 'text-embedding-nomic-embed-text-v1.5';
        $apiKey = $account->api_key ?: 'lm-studio';
        $baseUrl = $this->getBaseUrl($account);

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $dim = $request->dimensions ?: 768;
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

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ($apiKey && $apiKey !== 'lm-studio') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($baseUrl.'/embeddings', [
                    'model' => $model,
                    'input' => $request->input,
                ]);
        } catch (Exception $e) {
            throw new LlmDriverException("Błąd połączenia embedding LM Studio pod adresem [{$baseUrl}]: ".$e->getMessage());
        }

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new LlmDriverException('LM Studio embedding error: '.$response->body());
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
        $apiKey = $account->api_key ?: 'lm-studio';
        $baseUrl = $this->getBaseUrl($account);

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 10, 'Połączenie testowe LM Studio pomyślne (mock)');
        }

        try {
            $headers = [];
            if ($apiKey && $apiKey !== 'lm-studio') {
                $headers['Authorization'] = 'Bearer '.$apiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->get($baseUrl.'/models');

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $models = array_column($data['data'] ?? [], 'id');
                $modelList = ! empty($models)
                    ? ' (Wykryte modele: '.implode(', ', array_slice($models, 0, 3)).(count($models) > 3 ? '...' : '').')'
                    : ' (Brak załadowanych modeli)';

                return new ConnectionTestResult(true, $latency, 'Połączenie z serwerem LM Studio pomyślne'.$modelList);
            }

            return new ConnectionTestResult(
                false,
                $latency,
                'Błąd LM Studio (HTTP '.$response->status().'): '.substr($response->body(), 0, 150)
            );
        } catch (Exception $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            return new ConnectionTestResult(
                false,
                $latency,
                "Nie można połączyć się z LM Studio na [{$baseUrl}]: ".$e->getMessage().'. Upewnij się, że serwer lokalny w LM Studio jest uruchomiony.'
            );
        }
    }

    protected function getBaseUrl(LlmAccount $account): string
    {
        $url = $account->custom_base_url
            ?: ($account->provider?->base_url ?: 'http://localhost:1234/v1');

        $url = rtrim($url, '/');
        if (! str_ends_with($url, '/v1')) {
            $url .= '/v1';
        }

        return $url;
    }
}
