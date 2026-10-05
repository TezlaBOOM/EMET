<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'role',
        'content',
        'tokens_used',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
