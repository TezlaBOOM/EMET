<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioRunStep extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_RETRYING = 'retrying';

    protected $fillable = [
        'run_id',
        'node_id',
        'node_type',
        'status',
        'attempt',
        'input',
        'output',
        'agent_run_id',
        'skill_run_id',
        'tokens_in',
        'tokens_out',
        'cost',
        'started_at',
        'finished_at',
        'error',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'attempt' => 'integer',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ScenarioRun::class, 'run_id');
    }
}
