<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMEOUT = 'timeout';

    public $timestamps = false;

    protected $fillable = [
        'skill_id',
        'version_id',
        'agent_run_id',
        'scenario_run_step_id',
        'input_summary',
        'status',
        'duration_ms',
        'error',
        'created_at',
    ];

    protected $casts = [
        'input_summary' => 'array',
        'created_at' => 'datetime',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SkillVersion::class, 'version_id');
    }
}
