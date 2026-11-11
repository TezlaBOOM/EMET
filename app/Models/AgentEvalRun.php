<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvalRun extends Model
{
    protected $table = 'agent_eval_runs';

    public $timestamps = false;

    protected $fillable = [
        'suite_id',
        'agent_id',
        'model_a',
        'model_b',
        'status',
        'score_a',
        'score_b',
        'avg_latency_ms_a',
        'avg_latency_ms_b',
        'total_cost_usd_a',
        'total_cost_usd_b',
        'winner_model',
        'case_results',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suite_id' => 'integer',
            'agent_id' => 'integer',
            'score_a' => 'float',
            'score_b' => 'float',
            'avg_latency_ms_a' => 'integer',
            'avg_latency_ms_b' => 'integer',
            'total_cost_usd_a' => 'float',
            'total_cost_usd_b' => 'float',
            'case_results' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(AgentEvalSuite::class, 'suite_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}

