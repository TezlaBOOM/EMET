<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TelemetrySpan extends Model
{
    use HasUuids;

    protected $table = 'telemetry_spans';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'trace_id',
        'span_id',
        'parent_span_id',
        'name',
        'service_name',
        'span_kind',
        'status',
        'start_time_us',
        'end_time_us',
        'duration_ms',
        'attributes',
        'error_message',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time_us' => 'integer',
            'end_time_us' => 'integer',
            'duration_ms' => 'integer',
            'attributes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

