<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\ExternalChannel;

interface ChatOpsChannelInterface
{
    /**
     * Przetwarza przychodzące zdarzenie z kanału zewnętrznego (Telegram, Slack, Discord, Teams, Email)
     * lub interaktywną decyzję Human-in-the-Loop (Zatwierdź / Odrzuć).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     status: string,
     *     channel: string,
     *     event_type: string,
     *     reply_text?: string,
     *     resumed_run_id?: int
     * }
     */
    public function handleInboundWebhook(ExternalChannel $channel, array $payload): array;

    /**
     * Buduje interaktywny komunikat zatwierdzenia Human-in-the-Loop z przyciskami Zatwierdź / Odrzuć.
     *
     * @return array<string, mixed>
     */
    public function buildHumanApprovalPayload(ExternalChannel $channel, int $runId, string $nodeId, string $promptText): array;
}
