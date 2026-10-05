<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryRollup extends Model
{
    protected $fillable = [
        'period_type',
        'period_start',
        'agent_id',
        'provider_id',
        'model',
        'total_calls',
        'total_prompt_tokens',
        'total_completion_tokens',
        'total_tokens',
        'avg_ttft_ms',
        'avg_duration_ms',
        'p95_duration_ms',
        'total_cost_usd',
        'error_count',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'total_cost_usd' => 'float',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class);
    }
}
