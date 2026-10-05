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
// Spec: https://ai.google.dev/api/rest
class GeminiDriver implements LlmDriverInterface
{
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse
    {
        $startTime = microtime(true);
        $model = $request->model ?: ($account->provider?->default_model ?: 'gemini-1.5-flash');
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            return new CompletionResponse(
                content: "Mocked Gemini response for: " . ($request->messages[count($request->messages) - 1]['content'] ?? ''),
                model: $model,
                promptTokens: 20,
                completionTokens: 30,
                totalTokens: 50,
                latencyMs: max(10, $latency),
                ttftMs: 6,
                accountId: $account->id,
            );
        }

        // Konwersja komunikatów na format Gemini (contents / parts)
        $contents = [];
        foreach ($request->messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(60)->post($url, [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new RateLimitExceededException(
                message: 'Gemini rate limit exceeded: ' . $response->body(),
                retryAfterSeconds: $retryAfter,
                accountId: $account->id,
            );
        }

        if (! $response->successful()) {
            throw new LlmDriverException('Gemini API error (' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? [];

        return new CompletionResponse(
            content: $text,
            model: $model,
            promptTokens: $usage['promptTokenCount'] ?? 0,
            completionTokens: $usage['candidatesTokenCount'] ?? 0,
            totalTokens: $usage['totalTokenCount'] ?? 0,
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
        $model = $request->model ?: 'text-embedding-004';
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            $dim = $request->dimensions ?: 768;
            $mockVec = array_fill(0, $dim, 0.03);
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            return new EmbeddingResponse(
                embeddings: is_array($request->input) ? [$mockVec] : $mockVec,
                model: $model,
                totalTokens: 8,
                latencyMs: max(5, $latency),
                accountId: $account->id,
            );
        }

        $text = is_array($request->input) ? implode("\n", $request->input) : (string) $request->input;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'content' => ['parts' => [['text' => $text]]],
        ]);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->status() === 429) {
            throw new RateLimitExceededException('Gemini embedding rate limit exceeded', 60, $account->id);
        }

        if (! $response->successful()) {
            throw new LlmDriverException('Gemini embedding error: ' . $response->body());
        }

        $data = $response->json();
        $values = $data['embedding']['values'] ?? [];

        return new EmbeddingResponse(
            embeddings: is_array($request->input) ? [$values] : $values,
            model: $model,
            totalTokens: 10,
            latencyMs: $latency,
            accountId: $account->id,
        );
    }

    public function testConnection(LlmAccount $account): ConnectionTestResult
    {
        $startTime = microtime(true);
        $apiKey = $account->api_key;

        if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
            return new ConnectionTestResult(true, 12, 'Mock Gemini connection ok');
        }

        try {
            $response = Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
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
