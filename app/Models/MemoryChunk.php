<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryChunk extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'collection_id',
        'title',
        'content',
        'vector_point_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoryCollection::class, 'collection_id');
    }
}
