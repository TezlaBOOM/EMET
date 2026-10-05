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
            if (str_starts_with($apiKey, 'mock-') || str_starts_with($apiKey, 'test-') || app()->environment('testing')) {
                $latency = (int) round((microtime(true) - $startTime) * 1000);
                return [
                    'success' => true,
                    'latency_ms' => max(5, $latency),
                    'message' => 'Połączenie testowe pomyślne (mock driver)',
                ];
            }

            $response = match ($provider->driver) {
                'gemini' => Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $apiKey,
                ]),
                'openai' => Http::timeout(10)->withToken($apiKey)->get(($provider->base_url ?: 'https://api.openai.com/v1') . '/models'),
                'anthropic' => Http::timeout(10)->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])->get('https://api.anthropic.com/v1/models'),
                'ollama' => Http::timeout(5)->get(($provider->base_url ?: 'http://localhost:11434') . '/api/tags'),
                'openrouter' => Http::timeout(10)->withToken($apiKey)->get('https://openrouter.ai/api/v1/models'),
                default => Http::timeout(10)->withToken($apiKey)->get($provider->base_url ?: 'http://127.0.0.1:8000'),
            };

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'latency_ms' => $latency,
                    'message' => 'Połączenie pomyślne',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => 'Błąd HTTP: ' . $response->status() . ' – ' . substr($response->body(), 0, 150),
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
