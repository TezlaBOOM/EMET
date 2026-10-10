<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioTrigger extends Model
{
    use HasFactory;

    public const TYPE_MANUAL = 'manual';

    public const TYPE_CRON = 'cron';

    public const TYPE_WEBHOOK = 'webhook';

    protected $fillable = [
        'scenario_id',
        'type',
        'config',
        'token_hash',
        'enabled',
        'last_fired_at',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }
}
