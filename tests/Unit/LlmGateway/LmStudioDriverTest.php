<?php

declare(strict_types=1);

namespace Tests\Unit\LlmGateway;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\EmbeddingRequest;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Services\LlmGateway\Drivers\LmStudioDriver;
use App\Services\LlmGateway\Exceptions\LlmDriverException;
use App\Services\LlmGateway\Exceptions\RateLimitExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LmStudioDriverTest extends TestCase
{
    use RefreshDatabase;

    protected LmStudioDriver $driver;

    protected LlmProvider $provider;

    protected LlmAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new LmStudioDriver;

        $this->provider = LlmProvider::firstOrCreate(
            ['slug' => 'lmstudio'],
            [
                'name' => 'LM Studio (Lokalny)',
                'driver' => 'lmstudio',
                'base_url' => 'http://localhost:1234/v1',
                'default_model' => 'local-model',
                'is_active' => true,
            ]
        );

        $this->account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'LM Studio Local Server',
            'api_key' => 'lm-studio',
            'weight' => 10,
            'current_status' => 'active',
        ]);
    }

    public function test_lmstudio_driver_complete_with_mock_key(): void
    {
        $mockAccount = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'LM Studio Mock',
            'api_key' => 'mock-lmstudio-key',
            'weight' => 10,
        ]);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Testowe pytanie']],
            model: 'local-model',
            temperature: 0.7,
        );

        $response = $this->driver->complete($request, $mockAccount);

        $this->assertStringContainsString('Mocked LM Studio response', $response->content);
        $this->assertEquals('local-model', $response->model);
        $this->assertGreaterThan(0, $response->totalTokens);
        $this->assertEquals($mockAccount->id, $response->accountId);
    }

    public function test_lmstudio_driver_complete_with_fake_http(): void
    {
        Http::fake([
            'http://localhost:1234/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-lmstudio-123',
                'object' => 'chat.completion',
                'model' => 'qwen2.5-7b-instruct',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Odpowiedź wygenerowana lokalnie przez LM Studio.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 18,
                    'total_tokens' => 30,
                ],
            ], 200),
        ]);

        $request = new CompletionRequest(
            messages: [
                ['role' => 'system', 'content' => 'Jesteś asystentem.'],
                ['role' => 'user', 'content' => 'Cześć!'],
            ],
            model: 'qwen2.5-7b-instruct',
            temperature: 0.5,
            maxTokens: 500,
        );

        $response = $this->driver->complete($request, $this->account);

        $this->assertEquals('Odpowiedź wygenerowana lokalnie przez LM Studio.', $response->content);
        $this->assertEquals('qwen2.5-7b-instruct', $response->model);
        $this->assertEquals(30, $response->totalTokens);
        $this->assertEquals($this->account->id, $response->accountId);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'http://localhost:1234/v1/chat/completions'
                && $req['model'] === 'qwen2.5-7b-instruct'
                && count($req['messages']) === 2
                && $req['temperature'] === 0.5
                && $req['max_tokens'] === 500;
        });
    }

    public function test_lmstudio_driver_respects_custom_base_url(): void
    {
        $this->account->update([
            'custom_base_url' => 'http://192.168.0.50:1234',
        ]);

        Http::fake([
            'http://192.168.0.50:1234/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-lan-1',
                'choices' => [
                    [
                        'message' => ['content' => 'Odpowiedź z maszyny w LAN'],
                    ],
                ],
                'usage' => ['total_tokens' => 15],
            ], 200),
        ]);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Ping']],
        );

        $response = $this->driver->complete($request, $this->account);

        $this->assertEquals('Odpowiedź z maszyny w LAN', $response->content);
        Http::assertSent(fn (Request $req) => $req->url() === 'http://192.168.0.50:1234/v1/chat/completions');
    }

    public function test_lmstudio_driver_handles_rate_limit_429(): void
    {
        Http::fake([
            'http://localhost:1234/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Lokalny serwer przeciążony'],
            ], 429, ['Retry-After' => '15']),
        ]);

        $this->expectException(RateLimitExceededException::class);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Overload test']],
        );

        $this->driver->complete($request, $this->account);
    }

    public function test_lmstudio_driver_handles_server_error(): void
    {
        Http::fake([
            'http://localhost:1234/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Model not loaded'],
            ], 500),
        ]);

        $this->expectException(LlmDriverException::class);
        $this->expectExceptionMessage('LM Studio API error (500)');

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Crash test']],
        );

        $this->driver->complete($request, $this->account);
    }

    public function test_lmstudio_driver_stream_yields_words(): void
    {
        Http::fake([
            'http://localhost:1234/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jeden dwa trzy cztery']],
                ],
                'usage' => ['total_tokens' => 4],
            ], 200),
        ]);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Licz']],
        );

        $stream = iterator_to_array($this->driver->stream($request, $this->account));

        $this->assertNotEmpty($stream);
        $this->assertEquals('Jeden dwa trzy cztery', implode('', $stream));
    }

    public function test_lmstudio_driver_embed_with_fake_http(): void
    {
        Http::fake([
            'http://localhost:1234/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.12, 0.34, 0.56]],
                ],
                'usage' => ['total_tokens' => 5],
            ], 200),
        ]);

        $req = new EmbeddingRequest('Przykładowy tekst do wektoryzacji');
        $res = $this->driver->embed($req, $this->account);

        $this->assertEquals([0.12, 0.34, 0.56], $res->embeddings);
        $this->assertEquals(5, $res->totalTokens);
    }

    public function test_lmstudio_driver_test_connection_success_with_models(): void
    {
        Http::fake([
            'http://localhost:1234/v1/models' => Http::response([
                'data' => [
                    ['id' => 'qwen2.5-7b-instruct', 'object' => 'model'],
                    ['id' => 'llama-3.2-3b-instruct', 'object' => 'model'],
                ],
            ], 200),
        ]);

        $result = $this->driver->testConnection($this->account);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('qwen2.5-7b-instruct', $result->message);
    }

    public function test_lmstudio_driver_test_connection_failure(): void
    {
        Http::fake([
            'http://localhost:1234/v1/models' => Http::response(['error' => 'Connection refused'], 503),
        ]);

        $result = $this->driver->testConnection($this->account);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 503', $result->message);
    }
}
