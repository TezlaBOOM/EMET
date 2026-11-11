<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmSemanticCacheEntry extends Model
{
    protected $table = 'llm_semantic_cache_entries';

    protected $fillable = [
        'cache_key',
        'model',
        'system_prompt_hash',
        'prompt_text',
        'prompt_embedding',
        'response_content',
        'tool_calls',
        'prompt_tokens',
        'completion_tokens',
        'hit_count',
        'saved_tokens',
        'saved_cost_usd',
        'expires_at',
        'last_hit_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_embedding' => 'array',
            'tool_calls' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'hit_count' => 'integer',
            'saved_tokens' => 'integer',
            'saved_cost_usd' => 'float',
            'expires_at' => 'datetime',
            'last_hit_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

