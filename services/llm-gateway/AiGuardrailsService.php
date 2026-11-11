<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\AiGuardrailsInterface;
use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\CompletionResponse;
use App\Models\AuditLog;
use App\Models\LlmGuardrailEvent;
use App\Services\LlmGateway\Exceptions\GuardrailViolationException;
use Illuminate\Support\Facades\Schema;

class AiGuardrailsService implements AiGuardrailsInterface
{
    /**
     * Reguły detekcji Prompt Injection / Jailbreak.
     *
     * @var array<string, string>
     */
    private const JAILBREAK_PATTERNS = [
        'ignore_previous_instructions' => '/(?:ignore|disregard|forget)\s+(?:all\s+)?(?:previous|prior|above|system)\s+(?:instructions|prompts|rules|directives)|(?:zignoruj|pomiń|zapomnij)\s+(?:wszystkie\s+)?(?:poprzednie|wcześniejsze|powyższe|systemowe)\s+(?:instrukcje|polecenia|zasady)/iu',
        'dan_jailbreak_mode' => '/\b(?:do\s+anything\s+now|DAN\s+mode|tryb\s+DAN|jailbreak\s+mode|developer\s+mode\s+override|unrestricted\s+mode)\b/iu',
        'system_prompt_exfiltration' => '/(?:reveal|print|dump|output|show)\s+(?:your\s+)?(?:exact\s+)?(?:system\s+prompt|hidden\s+instructions|secret\s+api\s+key)|(?:ujawnij|wypisz|pokaż)\s+(?:swój\s+)?(?:prompt\s+systemowy|ukryte\s+instrukcje|tajny\s+klucz\s+api)/iu',
    ];

    /**
     * Reguły detekcji danych wrażliwych DLP (kolejność od najbardziej specyficznych).
     *
     * @var array<string, string>
     */
    private const DLP_PATTERNS = [
        'APIKEY' => '/\b(?:sk-(?:proj-)?[a-zA-Z0-9_\-]{16,}|AIza[0-9A-Za-z\-_]{25,}|ghp_[a-zA-Z0-9]{20,})\b/u',
        'IBAN' => '/\bPL\s?\d{2}(?:\s?\d{4}){6}\b/iu',
        'CARD' => '/\b(?:\d{4}[\s\-]){3}\d{4}\b|\b\d{16}\b/u',
        'PESEL' => '/\b\d{11}\b/u',
        'EMAIL' => '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/u',
        'PHONE' => '/(?:\+48[\s\-]?)?\b\d{3}[\s\-]\d{3}[\s\-]\d{3}\b/u',
    ];

    public function inspectAndMaskRequest(CompletionRequest $request, bool $isLocalModel = false): array
    {
        $enabled = (bool) ($request->metadata['guardrails_enabled']
            ?? config('agenthub.llm.guardrails.enabled', true));

        if (! $enabled) {
            return [
                'request' => $request,
                'replacements' => [],
                'matched_dlp_rules' => [],
                'jailbreak_detected' => false,
                'jailbreak_rules' => [],
            ];
        }

        $action = (string) ($request->metadata['jailbreak_action']
            ?? config('agenthub.llm.guardrails.jailbreak_action', 'block'));

        $dlpEnabled = (bool) ($request->metadata['dlp_enabled']
            ?? config('agenthub.llm.guardrails.dlp_enabled', true));

        $skipLocalDlp = (bool) config('agenthub.llm.guardrails.dlp_skip_local_models', true);
        $forceDlp = ! empty($request->metadata['force_dlp']);
        $shouldApplyDlp = $dlpEnabled && (! $isLocalModel || ! $skipLocalDlp || $forceDlp);

        $messages = $request->messages;
        $jailbreakRules = [];
        $replacements = [];
        $reverseMap = [];
        $matchedDlpRules = [];
        $counters = [];

        foreach ($messages as $idx => $message) {
            $role = strtolower((string) ($message['role'] ?? 'user'));
            $content = (string) ($message['content'] ?? '');

            if ($content === '') {
                continue;
            }

            // 1. Inspekcja Jailbreak / Prompt Injection (dla ról innych niż system)
            if ($role !== 'system') {
                foreach (self::JAILBREAK_PATTERNS as $ruleName => $pattern) {
                    if (preg_match($pattern, $content) === 1) {
                        $jailbreakRules[] = $ruleName;

                        if ($action === 'sanitize') {
                            $content = preg_replace($pattern, '[USUNIĘTO INSTRUKCJĘ NARUSZAJĄCĄ BEZPIECZEŃSTWO]', $content) ?? $content;
                        }
                    }
                }
            }

            // 2. Pseudonimizacja danych wrażliwych (DLP)
            if ($shouldApplyDlp && $role !== 'system') {
                foreach (self::DLP_PATTERNS as $type => $pattern) {
                    $content = preg_replace_callback(
                        $pattern,
                        function (array $matches) use ($type, &$replacements, &$reverseMap, &$matchedDlpRules, &$counters): string {
                            $originalValue = $matches[0];

                            if (isset($reverseMap[$originalValue])) {
                                return $reverseMap[$originalValue];
                            }

                            $counters[$type] = ($counters[$type] ?? 0) + 1;
                            $placeholder = "[PII_{$type}_{$counters[$type]}]";

                            $replacements[$placeholder] = $originalValue;
                            $reverseMap[$originalValue] = $placeholder;
                            $matchedDlpRules[] = $type;

                            return $placeholder;
                        },
                        $content
                    ) ?? $content;
                }
            }

            $messages[$idx]['content'] = $content;
        }

        $jailbreakRules = array_values(array_unique($jailbreakRules));
        $matchedDlpRules = array_values(array_unique($matchedDlpRules));
        $jailbreakDetected = ! empty($jailbreakRules);

        if ($jailbreakDetected) {
            $recordedAction = match ($action) {
                'sanitize' => 'sanitized',
                'audit' => 'audited',
                default => 'blocked',
            };

            $this->recordEvent(
                eventType: $recordedAction === 'blocked' ? 'jailbreak_blocked' : 'jailbreak_'.$recordedAction,
                severity: 'high',
                actionTaken: $recordedAction,
                matchedRules: $jailbreakRules,
                maskedItemsCount: 0,
                request: $request,
            );

            if ($action === 'block') {
                throw new GuardrailViolationException(
                    message: 'Zapytanie zostało zablokowane przez bramkę AI Guardrails: wykryto próbę Prompt Injection / Jailbreak ('.implode(', ', $jailbreakRules).').',
                    matchedRules: $jailbreakRules,
                    severity: 'high',
                );
            }
        }

        if (! empty($replacements)) {
            $this->recordEvent(
                eventType: 'dlp_masked',
                severity: 'medium',
                actionTaken: 'masked',
                matchedRules: $matchedDlpRules,
                maskedItemsCount: count($replacements),
                request: $request,
            );
        }

        $sanitizedRequest = new CompletionRequest(
            messages: $messages,
            model: $request->model,
            temperature: $request->temperature,
            maxTokens: $request->maxTokens,
            stop: $request->stop,
            tools: $request->tools,
            streamChannel: $request->streamChannel,
            metadata: $request->metadata,
        );

        return [
            'request' => $sanitizedRequest,
            'replacements' => $replacements,
            'matched_dlp_rules' => $matchedDlpRules,
            'jailbreak_detected' => $jailbreakDetected,
            'jailbreak_rules' => $jailbreakRules,
        ];
    }

    public function inspectAndRestoreResponse(CompletionResponse $response, array $replacements = []): CompletionResponse
    {
        $restoreEnabled = (bool) config('agenthub.llm.guardrails.dlp_restore_in_response', true);

        if ($restoreEnabled && ! empty($replacements) && $response->content !== '') {
            $response->content = strtr($response->content, $replacements);
        }

        return $response;
    }

    public function getStats(): array
    {
        $enabled = (bool) config('agenthub.llm.guardrails.enabled', true);
        $dlpEnabled = (bool) config('agenthub.llm.guardrails.dlp_enabled', true);
        $jailbreakAction = (string) config('agenthub.llm.guardrails.jailbreak_action', 'block');

        if (! Schema::hasTable('llm_guardrail_events')) {
            return [
                'enabled' => $enabled,
                'dlp_enabled' => $dlpEnabled,
                'jailbreak_action' => $jailbreakAction,
                'blocked_count' => 0,
                'masked_requests_count' => 0,
                'total_masked_items' => 0,
            ];
        }

        return [
            'enabled' => $enabled,
            'dlp_enabled' => $dlpEnabled,
            'jailbreak_action' => $jailbreakAction,
            'blocked_count' => (int) LlmGuardrailEvent::where('action_taken', 'blocked')->count(),
            'masked_requests_count' => (int) LlmGuardrailEvent::where('event_type', 'dlp_masked')->count(),
            'total_masked_items' => (int) LlmGuardrailEvent::sum('masked_items_count'),
        ];
    }

    /**
     * @param  list<string>  $matchedRules
     */
    private function recordEvent(
        string $eventType,
        string $severity,
        string $actionTaken,
        array $matchedRules,
        int $maskedItemsCount,
        CompletionRequest $request,
    ): void {
        $lastMessage = end($request->messages);
        $excerpt = is_array($lastMessage) ? mb_substr((string) ($lastMessage['content'] ?? ''), 0, 250) : null;

        if (Schema::hasTable('llm_guardrail_events')) {
            LlmGuardrailEvent::create([
                'event_type' => $eventType,
                'severity' => $severity,
                'action_taken' => $actionTaken,
                'matched_rules' => $matchedRules,
                'masked_items_count' => $maskedItemsCount,
                'model' => $request->model,
                'agent_id' => isset($request->metadata['agent_id']) ? (int) $request->metadata['agent_id'] : null,
                'prompt_excerpt' => $excerpt,
                'created_at' => now(),
            ]);
        }

        if (Schema::hasTable('audit_logs')) {
            AuditLog::record("guardrails.{$eventType}", 'LlmGateway', $request->model ?? 'default', [
                'severity' => $severity,
                'action_taken' => $actionTaken,
                'matched_rules' => $matchedRules,
                'masked_items_count' => $maskedItemsCount,
            ]);
        }
    }
}

