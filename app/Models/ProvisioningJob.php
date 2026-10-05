<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'instance_id',
        'action',
        'status',
        'logs',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(IntegrationInstance::class, 'instance_id');
    }

    public function appendLog(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->logs = ($this->logs ?? '') . "[{$timestamp}] {$message}\n";
        $this->save();
    }
}
