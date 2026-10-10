<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\GroupChatOrchestrator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupChatOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Agent $agent1;

    protected Agent $agent2;

    protected Agent $agent3;

    protected GroupChatOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->agent1 = Agent::create([
            'name' => 'Agent One',
            'slug' => 'agent-one',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Prompt Agenta 1',
        ]);

        $this->agent2 = Agent::create([
            'name' => 'Agent Two',
            'slug' => 'agent-two',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Prompt Agenta 2',
        ]);

        $this->agent3 = Agent::create([
            'name' => 'Moderator Agent',
            'slug' => 'moderator-agent',
            'primary_model' => 'gpt-4o',
            'system_prompt' => 'Prompt Moderatorka',
        ]);

        $this->orchestrator = app(GroupChatOrchestrator::class);
    }

    public function test_round_robin_orchestration_cycles_speakers(): void
    {
        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Grupa Round Robin',
            'mode' => 'group',
            'orchestration' => 'round_robin',
            'max_turns' => 10,
        ]);

        $this->orchestrator->addParticipant($conversation->id, $this->agent1->id);
        $this->orchestrator->addParticipant($conversation->id, $this->agent2->id);

        $speaker0 = $this->orchestrator->determineNextSpeaker($conversation->id, 0);
        $this->assertSame($this->agent1->id, $speaker0['next_agent_id']);

        $speaker1 = $this->orchestrator->determineNextSpeaker($conversation->id, 1);
        $this->assertSame($this->agent2->id, $speaker1['next_agent_id']);

        $speaker2 = $this->orchestrator->determineNextSpeaker($conversation->id, 2);
        $this->assertSame($this->agent1->id, $speaker2['next_agent_id']);
    }

    public function test_broadcast_orchestration_schedules_all_participants(): void
    {
        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Grupa Broadcast',
            'mode' => 'group',
            'orchestration' => 'broadcast',
            'max_turns' => 10,
        ]);

        $this->orchestrator->addParticipant($conversation->id, $this->agent1->id);
        $this->orchestrator->addParticipant($conversation->id, $this->agent2->id);

        $init = $this->orchestrator->initiateRound($conversation->id, 'Wiadomość do wszystkich');
        $this->assertSame(2, $init['turns_scheduled']);

        $speaker0 = $this->orchestrator->determineNextSpeaker($conversation->id, 0);
        $this->assertSame($this->agent1->id, $speaker0['next_agent_id']);

        $speaker1 = $this->orchestrator->determineNextSpeaker($conversation->id, 1);
        $this->assertSame($this->agent2->id, $speaker1['next_agent_id']);

        $speaker2 = $this->orchestrator->determineNextSpeaker($conversation->id, 2);
        $this->assertTrue($speaker2['is_complete']);
    }

    public function test_mention_orchestration_routes_to_mentioned_agent(): void
    {
        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Grupa Mention',
            'mode' => 'group',
            'orchestration' => 'mention',
            'lead_agent_id' => $this->agent1->id,
        ]);

        $this->orchestrator->addParticipant($conversation->id, $this->agent1->id, 'lead');
        $this->orchestrator->addParticipant($conversation->id, $this->agent2->id, 'member');

        // Bez mention - domyślny lead agent
        $this->orchestrator->initiateRound($conversation->id, 'Zwykła wiadomość');
        $speakerDefault = $this->orchestrator->determineNextSpeaker($conversation->id, 0);
        $this->assertSame($this->agent1->id, $speakerDefault['next_agent_id']);

        // Z @agent-two - przekierowanie do Agenta 2
        $this->orchestrator->initiateRound($conversation->id, 'Hej @agent-two co o tym sądzisz?');
        $speakerMention = $this->orchestrator->determineNextSpeaker($conversation->id, 0);
        $this->assertSame($this->agent2->id, $speakerMention['next_agent_id']);
        $this->assertSame('mention_detected', $speakerMention['reason']);
    }

    public function test_context_join_policies(): void
    {
        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Konwersacja polityki dołączenia',
            'mode' => 'group',
        ]);

        // Dodajemy 10 wiadomości w przeszłości
        for ($i = 1; $i <= 10; $i++) {
            ChatMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'assistant' : 'user',
                'content' => "Wiadomość {$i}",
                'created_at' => now()->subMinutes(20 - $i),
            ]);
        }

        // 1. Agent z polityką last_n = 3
        $this->orchestrator->addParticipant(
            conversationId: $conversation->id,
            agentId: $this->agent1->id,
            joinContext: 'last_n',
            joinContextN: 3
        );

        $contextLastN = $this->orchestrator->buildAgentContext($conversation->id, $this->agent1->id);
        // system prompt + 3 wiadomości = 4
        $this->assertCount(4, $contextLastN);
        $this->assertSame('Wiadomość 8', $contextLastN[1]['content']);

        // 2. Agent dołączający teraz z polityką none
        $this->orchestrator->addParticipant(
            conversationId: $conversation->id,
            agentId: $this->agent2->id,
            joinContext: 'none'
        );

        $contextNone = $this->orchestrator->buildAgentContext($conversation->id, $this->agent2->id);
        // tylko prompt systemowy (brak wiadomości po dołączeniu)
        $this->assertCount(1, $contextNone);
    }

    public function test_turn_limits_and_loop_detection(): void
    {
        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Limity test',
            'mode' => 'group',
            'max_turns' => 3,
        ]);

        // Dodajemy 3 wiadomości w bieżącej rundzie
        for ($i = 1; $i <= 3; $i++) {
            ChatMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => "Test {$i}",
                'round' => 1,
                'created_at' => now(),
            ]);
        }

        $limits = $this->orchestrator->validateTurnLimits($conversation->id);
        $this->assertFalse($limits['allowed']);
        $this->assertSame('max_turns_exceeded', $limits['violation_reason']);

        // Test detekcji pętli (identyczne wiadomości)
        $loopConversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'title' => 'Pętla test',
            'max_turns' => 20,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            ChatMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $loopConversation->id,
                'role' => 'assistant',
                'content' => 'Identyczna odpowiedź powtarzana w kółko',
                'round' => 1,
                'created_at' => now(),
            ]);
        }

        $loopLimits = $this->orchestrator->validateTurnLimits($loopConversation->id);
        $this->assertFalse($loopLimits['allowed']);
        $this->assertSame('loop_detected', $loopLimits['violation_reason']);
    }

    public function test_api_endpoints_manage_group_conversations(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/conversations', [
                'title' => 'API Grupa',
                'mode' => 'group',
                'orchestration' => 'round_robin',
                'agent_ids' => [$this->agent1->id, $this->agent2->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $conversationId = $response->json('conversation.id');

        // Dodanie kolejnego uczestnika
        $this->actingAs($this->admin)
            ->postJson("/api/v1/conversations/{$conversationId}/participants", [
                'agent_id' => $this->agent3->id,
                'role' => 'moderator',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Zatrzymanie konwersacji
        $this->actingAs($this->admin)
            ->postJson("/api/v1/conversations/{$conversationId}/stop")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
