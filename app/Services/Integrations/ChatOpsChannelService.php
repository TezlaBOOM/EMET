<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\ChatOpsChannelInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Models\ExternalChannel;

class ChatOpsChannelService implements ChatOpsChannelInterface
{
    public function __construct(
        protected ScenarioEngineInterface $scenarioEngine,
    ) {}

    public function handleInboundWebhook(ExternalChannel $channel, array $payload): array
    {
        $channel->update([
            'messages_handled_count' => $channel->messages_handled_count + 1,
            'last_event_at' => now(),
        ]);

        // 1. Obsługa interaktywnej decyzji Human-in-the-Loop (Zatwierdź / Odrzuć)
        $action = $payload['action'] ?? ($payload['callback_data']['action'] ?? null);
        $runId = isset($payload['run_id']) ? (int) $payload['run_id'] : (isset($payload['callback_data']['run_id']) ? (int) $payload['callback_data']['run_id'] : null);
        $nodeId = $payload['node_id'] ?? ($payload['callback_data']['node_id'] ?? null);

        if ($action !== null && $runId !== null && $nodeId !== null) {
            $approved = in_array(strtolower((string) $action), ['approve', 'approved', 'yes', 'accept'], true);
            $actor = (string) ($payload['user'] ?? $payload['username'] ?? 'chatops-operator');

            $this->scenarioEngine->resumeRun($runId, (string) $nodeId, [
                'approved' => $approved,
                'decision' => $approved ? 'approved' : 'rejected',
                'channel_platform' => $channel->platform,
                'actor' => $actor,
            ]);

            return [
                'status' => 'ok',
                'channel' => $channel->platform,
                'event_type' => 'human_in_the_loop_decision',
                'resumed_run_id' => $runId,
                'reply_text' => sprintf(
                    '[%s] Decyzja "%s" przez %s wznowiła scenariusz #%d na węźle %s.',
                    strtoupper($channel->platform),
                    $approved ? 'ZATWIERDŹ' : 'ODRZUĆ',
                    $actor,
                    $runId,
                    (string) $nodeId
                ),
            ];
        }

        // 2. Obsługa wiadomości tekstowej ChatOps -> wyzwolenie scenariusza lub odpowiedź agenta
        $messageText = trim((string) ($payload['text'] ?? $payload['message']['text'] ?? $payload['content'] ?? ''));

        if ($channel->scenario_id !== null) {
            $startedRunId = $this->scenarioEngine->startRun(
                scenarioId: $channel->scenario_id,
                versionId: null,
                input: [
                    'chatops_text' => $messageText,
                    'channel_platform' => $channel->platform,
                    'sender' => $payload['user'] ?? 'external_user',
                ],
                triggerType: 'chatops_'.$channel->platform
            );

            return [
                'status' => 'ok',
                'channel' => $channel->platform,
                'event_type' => 'scenario_triggered',
                'resumed_run_id' => $startedRunId,
                'reply_text' => sprintf('Uruchomiono scenariusz #%d z kanału %s.', $startedRunId, $channel->name),
            ];
        }

        $agentName = $channel->agent?->name ?? 'AgentHub Bot';

        return [
            'status' => 'ok',
            'channel' => $channel->platform,
            'event_type' => 'agent_message_reply',
            'reply_text' => sprintf('[%s @ %s] Odebrano wiadomość: %s', $agentName, strtoupper($channel->platform), $messageText),
        ];
    }

    public function buildHumanApprovalPayload(ExternalChannel $channel, int $runId, string $nodeId, string $promptText): array
    {
        return [
            'platform' => $channel->platform,
            'channel_slug' => $channel->slug,
            'text' => $promptText,
            'interactive_buttons' => [
                [
                    'label' => '✅ Zatwierdź (Approve)',
                    'style' => 'primary',
                    'callback_payload' => [
                        'action' => 'approve',
                        'run_id' => $runId,
                        'node_id' => $nodeId,
                    ],
                ],
                [
                    'label' => '❌ Odrzuć (Reject)',
                    'style' => 'danger',
                    'callback_payload' => [
                        'action' => 'reject',
                        'run_id' => $runId,
                        'node_id' => $nodeId,
                    ],
                ],
            ],
        ];
    }
}
