<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_COMPLETED = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'scenario_id',
        'version_id',
        'status',
        'trigger_type',
        'input',
        'output',
        'vars',
        'tokens_in',
        'tokens_out',
        'cost',
        'started_at',
        'finished_at',
        'error',
        'created_by',
        'trigger_id',
        'scheduled_for',
        'idempotency_key',
        'parent_run_id',
        'forked_from_node_id',
        'trace_id',
        'state_snapshots',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'vars' => 'array',
        'state_snapshots' => 'array',
        'parent_run_id' => 'integer',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(ScenarioTrigger::class, 'trigger_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'version_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ScenarioRunStep::class, 'run_id');
    }
}
