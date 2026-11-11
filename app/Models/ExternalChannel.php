<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExternalChannel extends Model
{
    protected $table = 'external_channels';

    protected $fillable = [
        'name',
        'slug',
        'platform',
        'agent_id',
        'scenario_id',
        'webhook_secret',
        'config',
        'is_active',
        'messages_handled_count',
        'last_event_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $channel): void {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name).'-'.Str::lower(Str::random(4));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'scenario_id' => 'integer',
            'webhook_secret' => 'encrypted',
            'config' => 'array',
            'is_active' => 'boolean',
            'messages_handled_count' => 'integer',
            'last_event_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }
}
