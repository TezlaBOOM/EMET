<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryRelation extends Model
{
    protected $table = 'memory_relations';

    protected $fillable = [
        'collection_id',
        'source_entity_id',
        'target_entity_id',
        'relation_type',
        'weight',
        'chunk_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_id' => 'integer',
            'source_entity_id' => 'integer',
            'target_entity_id' => 'integer',
            'weight' => 'float',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MemoryCollection::class, 'collection_id');
    }

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'target_entity_id');
    }
}

