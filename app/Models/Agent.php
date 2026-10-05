<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'runtime_type',
        'instance_id',
        'pool_id',
        'primary_model',
        'system_prompt',
        'temperature',
        'memory_collection_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(LlmAccountPool::class, 'pool_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(AgentSkill::class, 'agent_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'agent_id');
    }
}
