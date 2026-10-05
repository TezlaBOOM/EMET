<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoryCollection extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'vector_store',
        'embedding_provider_id',
        'embedding_model',
        'dimensions',
        'distance_metric',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function embeddingProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'embedding_provider_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(MemoryChunk::class, 'collection_id');
    }
}
