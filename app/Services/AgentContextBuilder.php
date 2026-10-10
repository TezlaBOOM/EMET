<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agent;
use App\Models\ChatConversation;

class AgentContextBuilder
{
    /**
     * Buduje tablicę wiadomości do przekazania do LLM.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function build(
        Agent $agent,
        string $currentUserMessage,
        ?ChatConversation $conversation = null,
        ?string $retrievedMemory = null
    ): array {
        $messages = [];

        // 1. Prompt systemowy oraz wiedza z pamięci wektorowej
        $systemPrompt = $agent->system_prompt ?? '';

        if (! empty($retrievedMemory)) {
            $memorySection = "\n\nKontekst z bazy wiedzy:\n".trim($retrievedMemory);
            $systemPrompt = trim($systemPrompt.$memorySection);
        }

        if (! empty($systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        // 2. Historia konwersacji (pomijana w trybie stateless)
        if (! $agent->isStateless() && $conversation !== null) {
            $window = $agent->context_window_messages ?? 20;

            $history = $conversation->messages()
                ->latest('created_at')
                ->take($window)
                ->get()
                ->reverse();

            foreach ($history as $msg) {
                $messages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }
        }

        // 3. Bieżąca wiadomość użytkownika (jeśli nie była jeszcze zapisana w historii)
        if (! empty($currentUserMessage)) {
            $lastMessage = end($messages);
            $alreadyPresent = $lastMessage !== false
                && ($lastMessage['role'] ?? '') === 'user'
                && ($lastMessage['content'] ?? '') === $currentUserMessage;

            if (! $alreadyPresent) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $currentUserMessage,
                ];
            }
        }

        return $messages;
    }
}
