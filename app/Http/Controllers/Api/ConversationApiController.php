<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\GroupChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ConversationApiController extends Controller
{
    public function __construct(
        protected GroupChatOrchestrator $orchestrator
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'mode' => ['nullable', 'in:single,group'],
            'orchestration' => ['nullable', 'in:mention,broadcast,round_robin,moderator'],
            'lead_agent_id' => ['nullable', 'exists:agents,id'],
            'moderator_agent_id' => ['nullable', 'exists:agents,id'],
            'max_turns' => ['nullable', 'integer', 'min:1', 'max:100'],
            'agent_ids' => ['nullable', 'array'],
            'agent_ids.*' => ['exists:agents,id'],
        ]);

        $mode = $validated['mode'] ?? 'single';
        $agentId = $validated['lead_agent_id'] ?? ($validated['agent_ids'][0] ?? null);

        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => Auth::id() ?? 1,
            'agent_id' => $agentId ?? Agent::first()?->id,
            'title' => $validated['title'],
            'mode' => $mode,
            'orchestration' => $validated['orchestration'] ?? 'mention',
            'lead_agent_id' => $validated['lead_agent_id'] ?? null,
            'moderator_agent_id' => $validated['moderator_agent_id'] ?? null,
            'max_turns' => $validated['max_turns'] ?? 20,
        ]);

        if (! empty($validated['agent_ids'])) {
            foreach ($validated['agent_ids'] as $idx => $aId) {
                $role = ($aId == $conversation->lead_agent_id) ? 'lead' : 'member';
                $this->orchestrator->addParticipant($conversation->id, (int) $aId, $role);
            }
        }

        return response()->json([
            'success' => true,
            'conversation' => $conversation->load('activeParticipants.agent'),
        ], 201);
    }

    public function addParticipant(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'role' => ['nullable', 'in:member,lead,moderator'],
            'join_context' => ['nullable', 'in:full,summary,last_n,none'],
            'join_context_n' => ['nullable', 'integer', 'min:1'],
        ]);

        $participant = $this->orchestrator->addParticipant(
            conversationId: $conversation->id,
            agentId: (int) $validated['agent_id'],
            role: $validated['role'] ?? 'member',
            joinContext: $validated['join_context'] ?? 'full',
            joinContextN: $validated['join_context_n'] ?? null
        );

        return response()->json([
            'success' => true,
            'participant' => $participant->load('agent'),
        ]);
    }

    public function removeParticipant(ChatConversation $conversation, Agent $agent): JsonResponse
    {
        $removed = $this->orchestrator->removeParticipant($conversation->id, $agent->id);

        return response()->json([
            'success' => $removed,
        ]);
    }

    public function stop(ChatConversation $conversation): JsonResponse
    {
        $this->orchestrator->stopConversation($conversation->id);

        return response()->json([
            'success' => true,
            'message' => 'Konwersacja zatrzymana',
        ]);
    }
}
