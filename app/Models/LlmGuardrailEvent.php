<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmGuardrailEvent extends Model
{
    protected $table = 'llm_guardrail_events';

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'severity',
        'action_taken',
        'matched_rules',
        'masked_items_count',
        'model',
        'agent_id',
        'prompt_excerpt',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matched_rules' => 'array',
            'masked_items_count' => 'integer',
            'agent_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

