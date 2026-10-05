<?php

declare(strict_types=1);

namespace Tests\Unit\LlmGateway;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use App\Models\LlmCall;
use App\Models\LlmProvider;
use App\Models\ModelPricing;
use App\Services\LlmGateway\LlmGateway;
use App\Services\LlmGateway\Strategies\LeastUsedStrategy;
use App\Services\LlmGateway\Strategies\PriorityFallbackStrategy;
use App\Services\LlmGateway\Strategies\RoundRobinStrategy;
use App\Services\LlmGateway\Strategies\WeightedStrategy;
use Database\Seeders\ModelPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PoolFailoverTest extends TestCase
{
    use RefreshDatabase;

    protected LlmGateway $gateway;
    protected LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModelPricingSeeder::class);

        $this->gateway = app(LlmGatewayInterface::class);

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI Gateway Test',
            'slug' => 'openai',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'is_active' => true,
        ]);
    }

    public function test_pool_strategies_account_selection(): void
    {
        $pool = LlmAccountPool::create([
            'name' => 'Test Smart Pool',
            'slug' => 'test-smart',
            'strategy' => 'round_robin',
            'is_active' => true,
        ]);

        $accountA = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Key A',
            'api_key' => 'mock-key-a',
            'weight' => 10,
        ]);

        $accountB = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Key B',
            'api_key' => 'mock-key-b',
            'weight' => 50,
        ]);

        $pool->accounts()->attach($accountA->id, ['priority' => 1, 'custom_weight' => 10]);
        $pool->accounts()->attach($accountB->id, ['priority' => 2, 'custom_weight' => 50]);

        $accounts = $pool->accounts()->get();

        // 1. Test RoundRobinStrategy
        $rr = new RoundRobinStrategy();
        $first = $rr->selectAccount($accounts, $pool);
        $second = $rr->selectAccount($accounts, $pool);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotEquals($first->id, $second->id);

        // 2. Test PriorityFallbackStrategy (powinien wybrać o priorytecie 1 -> Account A)
        $priorityStrategy = new PriorityFallbackStrategy();
        $selectedPriority = $priorityStrategy->selectAccount($accounts, $pool);
        $this->assertEquals($accountA->id, $selectedPriority->id);

        // 3. Test LeastUsedStrategy (Account A nigdy nieużyty, Account B użyty 5 min temu)
        $accountB->update(['last_used_at' => now()->subMinutes(5)]);
        $leastUsedStrategy = new LeastUsedStrategy();
        $selectedLeast = $leastUsedStrategy->selectAccount($pool->accounts()->get(), $pool);
        $this->assertEquals($accountA->id, $selectedLeast->id);

        // 4. Test WeightedStrategy
        $weighted = new WeightedStrategy();
        $selectedWeighted = $weighted->selectAccount($accounts, $pool);
        $this->assertContains($selectedWeighted->id, [$accountA->id, $accountB->id]);
    }

    public function test_failover_on_429_rate_limit_and_cooldown_activation(): void
    {
        $pool = LlmAccountPool::create([
            'name' => 'Failover Test Pool',
            'slug' => 'failover-pool',
            'strategy' => 'priority_fallback',
            'is_active' => true,
        ]);

        // Konto 1: Pierwszy wybór (priority 1), które zwróci 429
        $failingAccount = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Exhausted Rate Limit Key',
            'api_key' => 'sk-live-will-rate-limit',
            'weight' => 10,
        ]);

        // Konto 2: Drugi wybór (priority 2), działające mockowe konto
        $backupAccount = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Backup Active Key',
            'api_key' => 'mock-backup-key-ok',
            'weight' => 10,
        ]);

        $pool->accounts()->attach($failingAccount->id, ['priority' => 1]);
        $pool->accounts()->attach($backupAccount->id, ['priority' => 2]);

        // Mock HTTP: pierwsze konto zwraca kod 429 z nagłówkiem Retry-After: 45
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(
                ['error' => ['message' => 'Rate limit reached for requests']],
                429,
                ['Retry-After' => '45']
            ),
        ]);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Hello failover test']],
            model: 'gpt-4o'
        );

        $response = $this->gateway->complete($request, $pool);

        // Zapytanie zakończyło się sukcesem dzięki failoverowi na drugie konto
        $this->assertNotNull($response);
        $this->assertStringContainsString('Mocked OpenAI response', $response->content);
        $this->assertEquals($backupAccount->id, $response->accountId);

        // Sprawdzenie, czy pierwsze konto otrzymało status cooldown
        $failingAccount->refresh();
        $this->assertEquals('cooldown', $failingAccount->current_status);
        $this->assertNotNull($failingAccount->cooldown_until);
        $this->assertTrue($failingAccount->cooldown_until->isFuture());

        // Weryfikacja telemetrii w llm_calls: zapisano próbę zakończoną rate_limited oraz sukces
        $this->assertDatabaseHas('llm_calls', [
            'account_id' => $failingAccount->id,
            'status' => 'rate_limited',
        ]);

        $this->assertDatabaseHas('llm_calls', [
            'account_id' => $backupAccount->id,
            'status' => 'success',
        ]);
    }

    public function test_streaming_and_cost_estimation_telemetry(): void
    {
        $account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Streaming Account',
            'api_key' => 'mock-streaming-key',
            'weight' => 10,
        ]);

        $request = new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Opowiedz krótki dowcip']],
            model: 'gpt-4o'
        );

        $stream = $this->gateway->stream($request, null, $account);

        $chunks = [];
        foreach ($stream as $chunk) {
            $chunks[] = $chunk;
        }

        $fullText = implode('', $chunks);
        $this->assertNotEmpty($fullText);

        // Sprawdzenie zapisu telemetrii po zakończeniu strumienia
        $lastCall = LlmCall::where('account_id', $account->id)->latest('created_at')->first();
        $this->assertNotNull($lastCall);
        $this->assertEquals('success', $lastCall->status);
        $this->assertGreaterThan(0, $lastCall->total_tokens);
        $this->assertNotNull($lastCall->response_preview);
    }

    public function test_model_pricing_cost_calculation(): void
    {
        // 1M promptów gpt-4o = $2.50, 1M completions = $10.00
        // Dla 100_000 prompt i 50_000 completion:
        // (100000 / 1000000) * 2.50 = 0.25
        // (50000 / 1000000) * 10.00 = 0.50
        // Suma = 0.75 USD
        $cost = ModelPricing::calculateCost('gpt-4o', 100_000, 50_000);
        $this->assertEquals(0.75, $cost);
    }
}
