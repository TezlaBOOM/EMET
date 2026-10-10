<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerConfigRun extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'container_config_runs';

    protected $fillable = [
        'id',
        'container_id',
        'profile_id',
        'status',
        'diff_before',
        'diff_after',
        'logs',
        'error_message',
    ];

    protected $casts = [
        'diff_before' => 'array',
        'diff_after' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AutoConfigProfile::class, 'profile_id');
    }

    public function appendLog(string $message): void
    {
        $timestamp = now()->toDateTimeString();
        $this->logs = ($this->logs ? $this->logs."\n" : '')."[{$timestamp}] {$message}";
        $this->save();
    }
}
