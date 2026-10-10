<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Contracts\Chat\GroupChatOrchestratorInterface;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ConversationParticipant;
use App\Services\AgentContextBuilder;
use Illuminate\Support\Str;

class GroupChatOrchestrator implements GroupChatOrchestratorInterface
{
    public function __construct(
        protected AgentContextBuilder $contextBuilder
    ) {}

    public function initiateRound(string|int $conversationId, string $userMessageContent): array
    {
        $conversation = ChatConversation::with('activeParticipants.agent')->findOrFail($conversationId);
        $participants = $conversation->activeParticipants;

        if ($participants->isEmpty() && $conversation->agent_id) {
            // Dodajemy głównego agenta jako uczestnika
            $this->addParticipant($conversation->id, $conversation->agent_id, ConversationParticipant::ROLE_LEAD);
            $conversation->load('activeParticipants.agent');
            $participants = $conversation->activeParticipants;
        }

        $latestRound = (int) ($conversation->messages()->max('round') ?? 0);
        $newRound = $latestRound + 1;
        $roundId = (string) Str::uuid();

        // Zapisujemy wiadomość użytkownika
        ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessageContent,
            'round' => $newRound,
            'turn_id' => $roundId,
            'kind' => 'user',
            'created_at' => now(),
        ]);

        $turnsScheduled = match ($conversation->orchestration) {
            ChatConversation::ORCHESTRATION_BROADCAST => $participants->count(),
            ChatConversation::ORCHESTRATION_ROUND_ROBIN => min(1, $participants->count()),
            ChatConversation::ORCHESTRATION_MODERATOR => 1,
            default => 1, // mention
        };

        return [
            'round_id' => $roundId,
            'turns_scheduled' => $turnsScheduled,
        ];
    }

    public function determineNextSpeaker(string|int $conversationId, int $currentTurn): array
    {
        $conversation = ChatConversation::with('activeParticipants.agent')->findOrFail($conversationId);
        $participants = $conversation->activeParticipants->values();

        if ($participants->isEmpty()) {
            return ['next_agent_id' => null, 'reason' => 'no_participants', 'is_complete' => true];
        }

        $limitsCheck = $this->validateTurnLimits($conversationId);
        if (! $limitsCheck['allowed']) {
            return [
                'next_agent_id' => null,
                'reason' => $limitsCheck['violation_reason'] ?? 'limit_exceeded',
                'is_complete' => true,
            ];
        }

        // 1. Tryb BROADCAST: każdy aktywny agent wypowiada się raz w rundzie
        if ($conversation->orchestration === ChatConversation::ORCHESTRATION_BROADCAST) {
            if ($currentTurn >= $participants->count()) {
                return ['next_agent_id' => null, 'reason' => 'broadcast_round_finished', 'is_complete' => true];
            }
            $participant = $participants[$currentTurn];

            return [
                'next_agent_id' => $participant->agent_id,
                'reason' => 'broadcast_turn',
                'is_complete' => false,
            ];
        }

        // 2. Tryb ROUND-ROBIN: cykliczna kolejność mówców
        if ($conversation->orchestration === ChatConversation::ORCHESTRATION_ROUND_ROBIN) {
            if ($currentTurn >= $conversation->max_turns) {
                return ['next_agent_id' => null, 'reason' => 'max_turns_reached', 'is_complete' => true];
            }
            $index = $currentTurn % $participants->count();
            $participant = $participants[$index];

            return [
                'next_agent_id' => $participant->agent_id,
                'reason' => 'round_robin_turn',
                'is_complete' => false,
            ];
        }

        // 3. Tryb MODERATOR: moderator decyduje lub zaczyna rozmowę
        if ($conversation->orchestration === ChatConversation::ORCHESTRATION_MODERATOR) {
            $moderatorId = $conversation->moderator_agent_id ?? $conversation->lead_agent_id;

            return [
                'next_agent_id' => $moderatorId ?: $participants->first()->agent_id,
                'reason' => 'moderator_selection',
                'is_complete' => false,
            ];
        }

        // 4. Tryb MENTION: parsowanie @mention z ostatniej wiadomości
        $latestMessage = $conversation->messages()->reorder()->orderByDesc('round')->orderByDesc('created_at')->first();
        if ($latestMessage && ! empty($latestMessage->content)) {
            foreach ($participants as $participant) {
                $agent = $participant->agent;
                if (! $agent) {
                    continue;
                }
                $mentionPattern1 = '@'.$agent->slug;
                $mentionPattern2 = '@'.$agent->name;
                if (stripos($latestMessage->content, $mentionPattern1) !== false || stripos($latestMessage->content, $mentionPattern2) !== false) {
                    return [
                        'next_agent_id' => $agent->id,
                        'reason' => 'mention_detected',
                        'is_complete' => false,
                    ];
                }
            }
        }

        // Domyślny agent prowadzący (lead)
        $defaultAgentId = $conversation->lead_agent_id ?? $participants->first()->agent_id;

        return [
            'next_agent_id' => $defaultAgentId,
            'reason' => 'default_lead_agent',
            'is_complete' => false,
        ];
    }

    public function buildAgentContext(string|int $conversationId, int $agentId): array
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $agent = Agent::findOrFail($agentId);
        $participant = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('agent_id', $agentId)
            ->first();

        // Tryb bezstanowy - całkowity brak historii
        if ($agent->isStateless()) {
            $latestUserMessage = $conversation->messages()
                ->where('role', 'user')
                ->latest('created_at')
                ->first();

            return $this->contextBuilder->build($agent, $latestUserMessage?->content ?? '');
        }

        // Budowanie wiadomości z uwzględnieniem polityki join_context
        $messages = [];
        if (! empty($agent->system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $agent->system_prompt,
            ];
        }

        $joinPolicy = $participant?->join_context ?? ConversationParticipant::JOIN_FULL;
        $query = $conversation->messages()->reorder()->oldest('created_at');

        if ($joinPolicy === ConversationParticipant::JOIN_NONE && $participant?->joined_at) {
            $query->where('created_at', '>=', $participant->joined_at);
        } elseif ($joinPolicy === ConversationParticipant::JOIN_LAST_N) {
            $n = $participant->join_context_n ?? 5;
            $history = $conversation->messages()->reorder()->latest('created_at')->take($n)->get()->reverse();
            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }

            return $messages;
        }

        $allMessages = $query->get();

        foreach ($allMessages as $msg) {
            $content = $msg->content;
            // Oznaczanie wypowiedzi innych agentów jako źródło zewnętrzne
            if ($msg->kind === 'agent' && $msg->participant_id !== $participant?->id) {
                $author = $msg->participant?->agent?->name ?? 'Inny agent';
                $content = "[{$author}]: {$content}";
            }

            $messages[] = [
                'role' => $msg->role,
                'content' => $content,
            ];
        }

        return $messages;
    }

    public function validateTurnLimits(string|int $conversationId): array
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $latestRound = (int) ($conversation->messages()->max('round') ?? 1);

        $messagesInRound = $conversation->messages()
            ->where('round', $latestRound)
            ->count();

        if ($messagesInRound >= $conversation->max_turns) {
            return [
                'allowed' => false,
                'violation_reason' => 'max_turns_exceeded',
            ];
        }

        // Detekcja zapętlenia (ostatnie 4 wiadomości o identycznej treści lub hashu)
        $recentMessages = $conversation->messages()
            ->latest('created_at')
            ->take(4)
            ->pluck('content')
            ->all();

        if (count($recentMessages) >= 3 && count(array_unique($recentMessages)) === 1) {
            return [
                'allowed' => false,
                'violation_reason' => 'loop_detected',
            ];
        }

        return ['allowed' => true, 'violation_reason' => null];
    }

    public function stopConversation(string|int $conversationId, ?int $specificAgentId = null): bool
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $settings = $conversation->settings ?? [];
        $settings['stopped_at'] = now()->toIso8601String();
        $conversation->update(['settings' => $settings]);

        return true;
    }

    public function addParticipant(
        string|int $conversationId,
        int $agentId,
        string $role = ConversationParticipant::ROLE_MEMBER,
        string $joinContext = ConversationParticipant::JOIN_FULL,
        ?int $joinContextN = null
    ): ConversationParticipant {
        $conversation = ChatConversation::findOrFail($conversationId);
        $existing = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('agent_id', $agentId)
            ->first();

        if ($existing) {
            $existing->update([
                'left_at' => null,
                'role' => $role,
                'join_context' => $joinContext,
                'join_context_n' => $joinContextN,
            ]);

            return $existing;
        }

        $position = ConversationParticipant::where('conversation_id', $conversation->id)->count();

        return ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'agent_id' => $agentId,
            'role' => $role,
            'join_context' => $joinContext,
            'join_context_n' => $joinContextN,
            'joined_at' => now(),
            'position' => $position,
        ]);
    }

    public function removeParticipant(string|int $conversationId, int $agentId): bool
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('agent_id', $agentId)
            ->first();

        if ($participant) {
            $participant->update(['left_at' => now()]);

            return true;
        }

        return false;
    }
}
