<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationParticipant extends Model
{
    use HasFactory;

    public const ROLE_MEMBER = 'member';

    public const ROLE_LEAD = 'lead';

    public const ROLE_MODERATOR = 'moderator';

    public const JOIN_FULL = 'full';

    public const JOIN_SUMMARY = 'summary';

    public const JOIN_LAST_N = 'last_n';

    public const JOIN_NONE = 'none';

    protected $fillable = [
        'conversation_id',
        'agent_id',
        'role',
        'join_context',
        'join_context_n',
        'joined_at',
        'left_at',
        'position',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'position' => 'integer',
        'join_context_n' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'participant_id');
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
