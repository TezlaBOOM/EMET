<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\AgentContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        protected LlmGatewayInterface $llmGateway
    ) {}

    public function index(Request $request): View
    {
        $userId = Auth::id();
        $agents = Agent::where('is_active', true)->get();

        $conversationId = $request->query('conversation_id');
        $activeConversation = null;

        if ($conversationId) {
            $activeConversation = ChatConversation::with(['agent', 'messages'])
                ->where('user_id', $userId)
                ->find($conversationId);
        }

        if (! $activeConversation && $request->has('agent_id')) {
            $agent = Agent::find($request->query('agent_id'));
            if ($agent) {
                $activeConversation = ChatConversation::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'agent_id' => $agent->id,
                    'title' => 'Rozmowa z '.$agent->name,
                ]);
                $activeConversation->load(['agent', 'messages']);
            }
        }

        $conversations = ChatConversation::with('agent')
            ->where('user_id', $userId)
            ->latest()
            ->take(20)
            ->get();

        return view('module-chat::index', compact('agents', 'conversations', 'activeConversation'));
    }

    public function history(): View
    {
        $conversations = ChatConversation::with(['agent', 'messages'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('module-chat::history', compact('conversations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'exists:agents,id'],
            'initial_message' => ['nullable', 'string'],
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);

        $conversation = ChatConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => Auth::id(),
            'agent_id' => $agent->id,
            'title' => 'Rozmowa z '.$agent->name,
        ]);

        if (! empty($validated['initial_message'])) {
            ChatMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $validated['initial_message'],
                'created_at' => now(),
            ]);

            // Wywołanie LLM dla pierwszej wiadomości
            $this->processAssistantResponse($conversation, $validated['initial_message']);
        }

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id]);
    }

    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        // Zapis wiadomości użytkownika
        $userMessage = ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['content'],
            'created_at' => now(),
        ]);

        // Wywołanie asystenta
        $assistantMessage = $this->processAssistantResponse($conversation, $validated['content']);

        return response()->json([
            'success' => true,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ]);
    }

    public function streamMessage(Request $request, ChatConversation $conversation): StreamedResponse
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $content = $request->input('content');
        if (! $content) {
            abort(400, 'Brak treści wiadomości');
        }

        // Zapis wiadomości użytkownika
        ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $content,
            'created_at' => now(),
        ]);

        $agent = $conversation->agent;
        $messages = $this->buildMessageContext($conversation);

        $completionRequest = new CompletionRequest(
            messages: $messages,
            model: $agent->primary_model,
            temperature: (float) $agent->temperature,
            metadata: ['agent_id' => $agent->id]
        );

        $response = new StreamedResponse(function () use ($completionRequest, $agent, $conversation) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            $fullContent = '';
            $stream = $this->llmGateway->stream($completionRequest, $agent->pool);

            foreach ($stream as $chunk) {
                $fullContent .= $chunk;
                echo 'data: '.json_encode(['chunk' => $chunk])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            // Zapis pełnej odpowiedzi asystenta w bazie
            ChatMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $fullContent,
                'created_at' => now(),
            ]);

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    public function destroy(ChatConversation $conversation): RedirectResponse
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $conversation->delete();

        return redirect()->route('chat.index')->with('success', __('chat.conversation_deleted'));
    }

    protected function processAssistantResponse(ChatConversation $conversation, string $latestUserContent): ChatMessage
    {
        $agent = $conversation->agent;
        /** @var AgentContextBuilder $contextBuilder */
        $contextBuilder = app(AgentContextBuilder::class);
        $messages = $contextBuilder->build($agent, $latestUserContent, $conversation);

        $completionRequest = new CompletionRequest(
            messages: $messages,
            model: $agent->primary_model,
            temperature: (float) $agent->temperature,
            metadata: ['agent_id' => $agent->id]
        );

        $response = $this->llmGateway->complete($completionRequest, $agent->pool);

        return ChatMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $response->content,
            'tokens_used' => $response->totalTokens,
            'metadata' => [
                'model' => $response->model,
                'latency_ms' => $response->latencyMs,
                'account_id' => $response->accountId,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Buduje kontekst historii konwersacji z system promptem
     *
     * @return array<array{role: string, content: string}>
     */
    protected function buildMessageContext(ChatConversation $conversation): array
    {
        $messages = [];
        $agent = $conversation->agent;

        if ($agent && ! empty($agent->system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $agent->system_prompt,
            ];
        }

        // Pobieramy ostatnie 20 wiadomości
        $chatHistory = $conversation->messages()->latest('created_at')->take(20)->get()->reverse();

        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        return $messages;
    }
}
