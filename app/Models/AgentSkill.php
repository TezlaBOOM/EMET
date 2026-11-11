<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSkill extends Model
{
    protected $fillable = [
        'agent_id',
        'skill_id',
        'skill_version_id',
        'name',
        'driver',
        'config',
        'is_enabled',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'skill_id' => 'integer',
            'skill_version_id' => 'integer',
            'config' => 'array',
            'is_enabled' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
