<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LlmAccount;
use Exception;
use Illuminate\Support\Facades\Http;

class LlmConnectionTester
{
    /**
     * Testuje połączenie z kontem dostawcy i mierzy latencję
     *
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public function test(LlmAccount $account): array
    {
        $startTime = microtime(true);
        $provider = $account->provider;

        if (! $provider) {
            return [
                'success' => false,
                'latency_ms' => 0,
                'message' => 'Brak przypisanego dostawcy',
            ];
        }

        try {
            $apiKey = $account->api_key;

            // Weryfikacja formatu klucza testowego
            if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-')) {
                $latency = (int) round((microtime(true) - $startTime) * 1000);

                return [
                    'success' => true,
                    'latency_ms' => max(5, $latency),
                    'message' => 'Połączenie testowe pomyślne (mock driver)',
                ];
            }

            $baseUrl = rtrim($account->custom_base_url ?: ($provider->base_url ?: ''), '/');

            $response = match ($provider->driver) {
                'gemini' => Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $apiKey,
                ]),
                'openai' => Http::timeout(10)->withToken($apiKey)->get(($baseUrl ?: 'https://api.openai.com/v1').'/models'),
                'anthropic' => Http::timeout(10)->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])->get('https://api.anthropic.com/v1/models'),
                'ollama' => Http::timeout(5)->get(($baseUrl ?: 'http://localhost:11434').'/api/tags'),
                'lmstudio' => Http::timeout(5)
                    ->withHeaders($apiKey && $apiKey !== 'lm-studio' ? ['Authorization' => 'Bearer '.$apiKey] : [])
                    ->get((str_ends_with($baseUrl ?: 'http://localhost:1234/v1', '/v1')
                        ? ($baseUrl ?: 'http://localhost:1234/v1')
                        : ($baseUrl ?: 'http://localhost:1234').'/v1').'/models'),
                'openrouter' => Http::timeout(10)->withToken($apiKey)->get('https://openrouter.ai/api/v1/models'),
                default => Http::timeout(10)->withToken($apiKey)->get($baseUrl ?: 'http://127.0.0.1:8000'),
            };

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $details = '';
                if ($provider->driver === 'lmstudio') {
                    $models = array_column($response->json('data') ?? [], 'id');
                    if (! empty($models)) {
                        $details = ' (Załadowane modele: '.implode(', ', array_slice($models, 0, 3)).(count($models) > 3 ? '...' : '').')';
                    }
                }

                return [
                    'success' => true,
                    'latency_ms' => $latency,
                    'message' => 'Połączenie pomyślne'.$details,
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => 'Błąd HTTP: '.$response->status().' – '.substr($response->body(), 0, 150),
            ];
        } catch (Exception $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => $e->getMessage(),
            ];
        }
    }
}
