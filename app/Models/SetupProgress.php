<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetupProgress extends Model
{
    use HasFactory;

    protected $table = 'setup_progress';

    protected $fillable = [
        'step_key',
        'title',
        'module',
        'is_completed',
        'completed_at',
        'completed_by',
        'metadata',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public static function markStep(string $stepKey, bool $completed = true, ?int $userId = null): self
    {
        return self::updateOrCreate(
            ['step_key' => $stepKey],
            [
                'is_completed' => $completed,
                'completed_at' => $completed ? now() : null,
                'completed_by' => $completed ? $userId : null,
            ]
        );
    }
}
