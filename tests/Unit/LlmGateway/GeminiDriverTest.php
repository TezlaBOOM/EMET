<?php

declare(strict_types=1);

namespace Tests\Unit\LlmGateway;

use App\Contracts\Llm\CompletionRequest;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Services\LlmGateway\Drivers\GeminiDriver;
use App\Services\LlmGateway\Exceptions\LlmDriverException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiDriverTest extends TestCase
{
    use RefreshDatabase;

    protected GeminiDriver $driver;

    protected LlmProvider $provider;

    protected LlmAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new GeminiDriver;

        $this->provider = LlmProvider::create([
            'name' => 'Google Gemini',
            'slug' => 'gemini',
            'driver' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com',
            'default_model' => 'gemini-1.5-flash',
            'is_active' => true,
        ]);

        $this->account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Gemini Real Account',
            'api_key' => 'actual-gemini-api-key',
            'weight' => 10,
            'current_status' => 'active',
        ]);
    }

    public function test_gemini_driver_formats_payload_and_normalizes_model(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Odpowiedź od Gemini'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 15,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 35,
                ],
            ], 200),
        ]);

        $request = new CompletionRequest(
            messages: [
                ['role' => 'system', 'content' => 'Jesteś pomocnym asystentem.'],
                ['role' => 'user', 'content' => 'Wiadomość 1'],
                ['role' => 'user', 'content' => 'Wiadomość 2 (powinna zostać połączona)'],
            ],
            model: 'gemini',
            temperature: 0.7
        );

        $response = $this->driver->complete($request, $this->account);

        $this->assertEquals('Odpowiedź od Gemini', $response->content);
        $this->assertEquals('gemini-1.5-flash', $response->model);
        $this->assertEquals(35, $response->totalTokens);

        Http::assertSent(function (Request $httpReq) {
            $data = $httpReq->data();

            // System prompt powinien trafić do systemInstruction
            $hasSystemInstruction = isset($data['systemInstruction']['parts'][0]['text'])
                && $data['systemInstruction']['parts'][0]['text'] === 'Jesteś pomocnym asystentem.';

            // Kolejne wiadomości użytkownika powinny być połączone w 1 turn (wymóg naprzemienności ról w Gemini)
            $contentsCount = count($data['contents']);
            $firstRole = $data['contents'][0]['role'] ?? null;

            return $hasSystemInstruction && $contentsCount === 1 && $firstRole === 'user';
        });
    }

    public function test_gemini_driver_extracts_clean_error_message(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $request = new CompletionRequest(
            messages: [
                ['role' => 'user', 'content' => 'Cześć'],
            ],
            model: 'gemini-1.5-flash'
        );

        $this->expectException(LlmDriverException::class);
        $this->expectExceptionMessage('Gemini API error (400): API key not valid. Please pass a valid API key.');

        $this->driver->complete($request, $this->account);
    }
}
