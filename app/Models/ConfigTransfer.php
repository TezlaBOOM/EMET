<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigTransfer extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'config_transfers';

    protected $fillable = [
        'id',
        'type',
        'mode',
        'status',
        'file_path',
        'file_name',
        'file_hash',
        'sections',
        'stats',
        'diff_report',
        'has_secrets',
        'backup_path',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'sections' => 'array',
        'stats' => 'array',
        'diff_report' => 'array',
        'has_secrets' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
