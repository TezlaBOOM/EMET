<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\ModelPricingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentChatStreamTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected LlmProvider $provider;
    protected LlmAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ModelPricingSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@test.lan',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI Test',
            'slug' => 'openai',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'is_active' => true,
        ]);

        $this->account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Test Mock Key',
            'api_key' => 'mock-test-key-chat',
            'weight' => 10,
            'current_status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_agents_and_chat(): void
    {
        $this->get('/agents')->assertRedirect('/login');
        $this->get('/chat')->assertRedirect('/login');
    }

    public function test_admin_can_create_edit_and_delete_agent(): void
    {
        // 1. Utworzenie agenta
        $response = $this->actingAs($this->admin)->post('/agents', [
            'name' => 'Badacz Testowy',
            'slug' => 'badacz-testowy',
            'description' => 'Testowy agent analityczny',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Jesteś asystentem badawczym.',
            'temperature' => 0.5,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/agents');
        $this->assertDatabaseHas('agents', [
            'name' => 'Badacz Testowy',
            'slug' => 'badacz-testowy',
            'primary_model' => 'gpt-4o',
        ]);

        $agent = Agent::where('slug', 'badacz-testowy')->first();
        $this->assertNotNull($agent);
        $this->assertGreaterThan(0, $agent->skills()->count());

        // 2. Toggle skilla
        $skill = $agent->skills()->first();
        $this->assertTrue($skill->is_enabled);

        $toggleResponse = $this->actingAs($this->admin)->post("/agents/{$agent->id}/skills/{$skill->id}/toggle");
        $toggleResponse->assertRedirect();
        $this->assertFalse($skill->fresh()->is_enabled);

        // 3. Edycja agenta
        $updateResponse = $this->actingAs($this->admin)->put("/agents/{$agent->id}", [
            'name' => 'Badacz Zaktualizowany',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'temperature' => 0.8,
            'is_active' => 1,
        ]);
        $updateResponse->assertRedirect();
        $this->assertEquals('Badacz Zaktualizowany', $agent->fresh()->name);

        // 4. Usunięcie agenta
        $deleteResponse = $this->actingAs($this->admin)->delete("/agents/{$agent->id}");
        $deleteResponse->assertRedirect('/agents');
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_user_can_start_conversation_and_send_message(): void
    {
        $agent = Agent::create([
            'name' => 'Asystent Rozmówca',
            'slug' => 'asystent-rozmowca',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Odpowiadaj krótko i na temat.',
            'temperature' => 0.7,
            'is_active' => true,
        ]);

        // Rozpoczęcie nowej konwersacji z pierwszą wiadomością
        $response = $this->actingAs($this->admin)->post('/chat', [
            'agent_id' => $agent->id,
            'initial_message' => 'Cześć agencie!',
        ]);

        $response->assertRedirect();
        $conversation = ChatConversation::where('agent_id', $agent->id)->first();
        $this->assertNotNull($conversation);

        // Powinna być wiadomość użytkownika i asystenta
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Cześć agencie!',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);

        // Wysłanie kolejnej wiadomości przez AJAX endpoint
        $sendResponse = $this->actingAs($this->admin)->postJson("/chat/{$conversation->id}/messages", [
            'content' => 'Drugie pytanie testowe',
        ]);

        $sendResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Drugie pytanie testowe',
        ]);

        $this->assertEquals(4, $conversation->messages()->count());
    }

    public function test_user_can_stream_message(): void
    {
        $agent = Agent::create([
            'name' => 'Streaming Agent',
            'slug' => 'streaming-agent',
            'runtime_type' => 'native',
            'primary_model' => 'gpt-4o',
            'temperature' => 0.7,
            'is_active' => true,
        ]);

        $conversation = ChatConversation::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->admin->id,
            'agent_id' => $agent->id,
            'title' => 'Strumieniowana rozmowa',
        ]);

        $response = $this->actingAs($this->admin)->post("/chat/{$conversation->id}/stream", [
            'content' => 'Podaj krótką sentencję',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');

        // Weryfikacja zapisu wiadomości użytkownika i asystenta
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Podaj krótką sentencję',
        ]);
    }
}
