<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TelemetryEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentTelemetryBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TelemetryEvent $event
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('telemetry.agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.event';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'agent_id' => $this->event->agent_id,
            'run_id' => $this->event->run_id,
            'type' => $this->event->type,
            'payload' => $this->event->payload,
            'created_at' => $this->event->created_at?->toIso8601String(),
        ];
    }
}
