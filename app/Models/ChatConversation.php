<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasUuids;

    public const MODE_SINGLE = 'single';

    public const MODE_GROUP = 'group';

    public const ORCHESTRATION_MENTION = 'mention';

    public const ORCHESTRATION_BROADCAST = 'broadcast';

    public const ORCHESTRATION_ROUND_ROBIN = 'round_robin';

    public const ORCHESTRATION_MODERATOR = 'moderator';

    protected $fillable = [
        'id',
        'user_id',
        'agent_id',
        'title',
        'is_archived',
        'mode',
        'orchestration',
        'lead_agent_id',
        'moderator_agent_id',
        'max_turns',
        'max_rounds',
        'limits',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'max_turns' => 'integer',
            'max_rounds' => 'integer',
            'limits' => 'array',
            'settings' => 'array',
        ];
    }

    public function isGroup(): bool
    {
        return $this->mode === self::MODE_GROUP;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function leadAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'lead_agent_id');
    }

    public function moderatorAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'moderator_agent_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id')
            ->whereNull('left_at')
            ->orderBy('position');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
