<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmCall extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'agent_id',
        'account_id',
        'provider_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'ttft_ms',
        'duration_ms',
        'estimated_cost_usd',
        'status',
        'prompt_preview',
        'response_preview',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'ttft_ms' => 'integer',
            'duration_ms' => 'integer',
            'estimated_cost_usd' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LlmAccount::class, 'account_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }
}
