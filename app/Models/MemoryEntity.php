<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemoryEntity extends Model
{
    protected $table = 'memory_entities';

    protected $fillable = [
        'collection_id',
        'chunk_id',
        'name',
        'normalized_name',
        'entity_type',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoryCollection::class, 'collection_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(MemoryChunk::class, 'chunk_id');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(MemoryRelation::class, 'source_entity_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(MemoryRelation::class, 'target_entity_id');
    }
}

