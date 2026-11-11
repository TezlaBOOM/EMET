<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCooperation extends Model
{
    public const TOPOLOGY_ROUND_TABLE = 'round_table';

    public const TOPOLOGY_PIPELINE = 'pipeline';

    public const TOPOLOGY_SUPERVISOR = 'supervisor';

    public const TOPOLOGY_DEBATE_CONSENSUS = 'debate_consensus';

    public const THERMAL_NOMINAL = 'nominal';

    public const THERMAL_WARM = 'warm';

    public const THERMAL_HOT = 'hot';

    public const THERMAL_OVERHEATED = 'overheated';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'topology',
        'coordinator_agent_id',
        'participant_agent_ids',
        'participant_roles',
        'overheat_guard_enabled',
        'max_rounds',
        'max_tokens_budget',
        'max_temperature_score',
        'cooldown_seconds',
        'repetition_threshold',
        'auto_synthesize_on_overheat',
        'current_temperature',
        'thermal_status',
        'status',
        'total_tokens_used',
        'total_turns_executed',
        'context_reloads_count',
        'last_task_prompt',
        'preserved_context',
        'final_synthesis',
        'discussion_log',
        'overheat_events',
        'cooled_down_at',
    ];

    protected function casts(): array
    {
        return [
            'participant_agent_ids' => 'array',
            'participant_roles' => 'array',
            'overheat_guard_enabled' => 'boolean',
            'max_rounds' => 'integer',
            'max_tokens_budget' => 'integer',
            'max_temperature_score' => 'float',
            'cooldown_seconds' => 'integer',
            'repetition_threshold' => 'float',
            'auto_synthesize_on_overheat' => 'boolean',
            'current_temperature' => 'float',
            'total_tokens_used' => 'integer',
            'total_turns_executed' => 'integer',
            'context_reloads_count' => 'integer',
            'discussion_log' => 'array',
            'overheat_events' => 'array',
            'cooled_down_at' => 'datetime',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'coordinator_agent_id');
    }

    /**
     * Zwraca kolekcję spiętych agentów w kolejności zdefiniowanej w kooperacji.
     *
     * @return Collection<int, Agent>
     */
    public function participantAgents(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->participant_agent_ids ?? [])));
        if (empty($ids)) {
            return new Collection;
        }

        $agentsById = Agent::with('skills')->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = new Collection;

        foreach ($ids as $id) {
            if ($agentsById->has($id)) {
                $ordered->push($agentsById->get($id));
            }
        }

        return $ordered;
    }
}
