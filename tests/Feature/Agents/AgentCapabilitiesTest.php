<?php

declare(strict_types=1);

namespace Tests\Feature\Agents;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\TelemetryEvent;
use App\Models\User;
use App\Services\AgentContextBuilder;
use App\Services\AgentEgressProxyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AgentCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
    }

    public function test_agent_model_validates_internet_and_context_modes(): void
    {
        $agent = Agent::create([
            'name' => 'Agent Test',
            'slug' => 'agent-test',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'allowlist',
            'context_mode' => 'stateless',
        ]);

        $this->assertSame('allowlist', $agent->internet_mode);
        $this->assertSame('stateless', $agent->context_mode);
        $this->assertTrue($agent->isStateless());
        $this->assertTrue($agent->hasInternetAccess());
        $this->assertFalse($agent->isOpenInternet());
        $this->assertTrue($agent->internet_enabled);

        // Zmiana przez internet_enabled na false
        $agent->internet_enabled = false;
        $agent->save();
        $this->assertSame('off', $agent->internet_mode);

        // Niepoprawny internet_mode
        $this->expectException(InvalidArgumentException::class);
        $agent->internet_mode = 'invalid_mode';
        $agent->save();
    }

    public function test_agent_context_builder_in_stateless_mode_ignores_history(): void
    {
        $agent = Agent::create([
            'name' => 'Stateless Agent',
            'slug' => 'stateless-agent',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Jesteś bezstanowym asystentem.',
            'context_mode' => 'stateless',
        ]);

        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'agent_id' => $agent->id,
            'title' => 'Rozmowa testowa',
        ]);

        // Poprzednie wiadomości w konwersacji
        ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Nazywam się Jan i mój ulubiony kolor to niebieski.',
            'created_at' => now()->subMinute(),
        ]);

        ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Zapamiętałem, Janie!',
            'created_at' => now()->subSeconds(30),
        ]);

        $builder = app(AgentContextBuilder::class);
        $messages = $builder->build($agent, 'Jaki jest mój ulubiony kolor?', $conversation);

        // W trybie stateless powinny być tylko: system prompt i bieżąca wiadomość
        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('Jesteś bezstanowym asystentem.', $messages[0]['content']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('Jaki jest mój ulubiony kolor?', $messages[1]['content']);
    }

    public function test_agent_context_builder_in_stateful_mode_includes_history_and_memory(): void
    {
        $agent = Agent::create([
            'name' => 'Stateful Agent',
            'slug' => 'stateful-agent',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Jesteś asystentem.',
            'context_mode' => 'stateful',
        ]);

        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'agent_id' => $agent->id,
            'title' => 'Rozmowa testowa',
        ]);

        ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pierwsza wiadomość',
            'created_at' => now()->subMinute(),
        ]);

        $builder = app(AgentContextBuilder::class);
        $messages = $builder->build(
            agent: $agent,
            currentUserMessage: 'Druga wiadomość',
            conversation: $conversation,
            retrievedMemory: 'Fakt z bazy wiedzy: Ziemia krąży wokół Słońca.'
        );

        $this->assertCount(3, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertStringContainsString('Fakt z bazy wiedzy', $messages[0]['content']);
        $this->assertSame('Pierwsza wiadomość', $messages[1]['content']);
        $this->assertSame('Druga wiadomość', $messages[2]['content']);
    }

    public function test_egress_proxy_blocks_when_internet_mode_is_off(): void
    {
        $agent = Agent::create([
            'name' => 'Offline Agent',
            'slug' => 'offline-agent',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'off',
        ]);

        $proxy = app(AgentEgressProxyService::class);
        $result = $proxy->validateRequest($agent, 'https://api.github.com/repos');

        $this->assertFalse($result['allowed']);
        $this->assertSame('internet_disabled', $result['reason']);

        $blockedEvent = TelemetryEvent::where('agent_id', $agent->id)
            ->where('type', 'internet.blocked')
            ->first();

        $this->assertNotNull($blockedEvent);
        $this->assertSame('internet_disabled', $blockedEvent->payload['reason']);
    }

    public function test_egress_proxy_enforces_domain_allowlist(): void
    {
        $agent = Agent::create([
            'name' => 'Allowlist Agent',
            'slug' => 'allowlist-agent',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'allowlist',
        ]);

        $proxy = app(AgentEgressProxyService::class);
        $allowedDomains = ['api.github.com'];

        // Dozwolona domena
        $allowedResult = $proxy->validateRequest($agent, 'https://api.github.com/users', $allowedDomains);
        $this->assertTrue($allowedResult['allowed']);
        $this->assertNotNull($allowedResult['token']);

        // Niedozwolona domena
        $blockedResult = $proxy->validateRequest($agent, 'https://malicious-domain.com/data', $allowedDomains);
        $this->assertFalse($blockedResult['allowed']);
        $this->assertSame('domain_not_allowlisted', $blockedResult['reason']);

        $blockedEvent = TelemetryEvent::where('agent_id', $agent->id)
            ->where('type', 'internet.blocked')
            ->whereJsonContains('payload->reason', 'domain_not_allowlisted')
            ->first();
        $this->assertNotNull($blockedEvent);
    }

    public function test_egress_proxy_blocks_ssrf_attempts(): void
    {
        $agent = Agent::create([
            'name' => 'Open Agent',
            'slug' => 'open-agent',
            'primary_model' => 'gpt-4o',
            'internet_mode' => 'open',
        ]);

        $proxy = app(AgentEgressProxyService::class);

        $ssrfTargets = [
            'http://localhost:8000/internal',
            'http://127.0.0.1/admin',
            'http://169.254.169.254/latest/meta-data',
        ];

        foreach ($ssrfTargets as $targetUrl) {
            $result = $proxy->validateRequest($agent, $targetUrl);
            $this->assertFalse($result['allowed'], "Powinno zablokować SSRF dla: {$targetUrl}");
            $this->assertSame('ssrf_blocked', $result['reason']);
        }
    }

    public function test_docs_check_artisan_command_passes(): void
    {
        $this->artisan('docs:check')
            ->assertExitCode(0);
    }
}
