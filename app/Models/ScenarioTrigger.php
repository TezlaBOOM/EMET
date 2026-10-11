<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioTrigger extends Model
{
    use HasFactory;

    public const TYPE_MANUAL = 'manual';

    public const TYPE_CRON = 'cron';

    public const TYPE_WEBHOOK = 'webhook';

    public const SCHEDULE_ONCE = 'once';

    public const SCHEDULE_INTERVAL = 'interval';

    public const SCHEDULE_DAILY = 'daily';

    public const SCHEDULE_WEEKLY = 'weekly';

    public const SCHEDULE_MONTHLY = 'monthly';

    public const SCHEDULE_CRON = 'cron';

    public const STATE_ACTIVE = 'active';

    public const STATE_PAUSED = 'paused';

    public const STATE_FINISHED = 'finished';

    public const STATE_ERROR = 'error';

    protected $fillable = [
        'scenario_id',
        'name',
        'type',
        'schedule_type',
        'schedule',
        'timezone',
        'starts_at',
        'ends_at',
        'max_runs',
        'runs_count',
        'input',
        'overlap_policy',
        'misfire_policy',
        'next_run_at',
        'last_run_at',
        'last_run_id',
        'consecutive_failures',
        'last_error',
        'state',
        'config',
        'token_hash',
        'enabled',
        'last_fired_at',
    ];

    protected $casts = [
        'config' => 'array',
        'schedule' => 'array',
        'input' => 'array',
        'enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_fired_at' => 'datetime',
        'runs_count' => 'integer',
        'max_runs' => 'integer',
        'consecutive_failures' => 'integer',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScenarioRun::class, 'trigger_id');
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->state === self::STATE_ACTIVE;
    }
}
