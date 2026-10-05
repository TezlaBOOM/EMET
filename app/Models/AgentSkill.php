<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSkill extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'driver',
        'config',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
