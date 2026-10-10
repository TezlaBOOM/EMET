<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Agent extends Model
{
    use HasFactory;

    public const INTERNET_OFF = 'off';

    public const INTERNET_ALLOWLIST = 'allowlist';

    public const INTERNET_OPEN = 'open';

    public const CONTEXT_STATEFUL = 'stateful';

    public const CONTEXT_STATELESS = 'stateless';

    public const ALLOWED_INTERNET_MODES = [
        self::INTERNET_OFF,
        self::INTERNET_ALLOWLIST,
        self::INTERNET_OPEN,
    ];

    public const ALLOWED_CONTEXT_MODES = [
        self::CONTEXT_STATEFUL,
        self::CONTEXT_STATELESS,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'runtime_type',
        'instance_id',
        'pool_id',
        'primary_model',
        'system_prompt',
        'temperature',
        'memory_collection_id',
        'is_active',
        'internet_mode',
        'context_mode',
        'context_window_messages',
        'internet_enabled',
    ];

    protected $appends = [
        'internet_enabled',
    ];

    protected $attributes = [
        'internet_mode' => self::INTERNET_OFF,
        'context_mode' => self::CONTEXT_STATEFUL,
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'is_active' => 'boolean',
            'context_window_messages' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });

        static::saving(function (Agent $agent) {
            $agent->internet_mode = $agent->internet_mode ?: self::INTERNET_OFF;
            $agent->context_mode = $agent->context_mode ?: self::CONTEXT_STATEFUL;

            if (! in_array($agent->internet_mode, self::ALLOWED_INTERNET_MODES, true)) {
                throw new InvalidArgumentException(
                    "Niedozwolony internet_mode: {$agent->internet_mode}. Dozwolone: ".implode(', ', self::ALLOWED_INTERNET_MODES)
                );
            }

            if (! in_array($agent->context_mode, self::ALLOWED_CONTEXT_MODES, true)) {
                throw new InvalidArgumentException(
                    "Niedozwolony context_mode: {$agent->context_mode}. Dozwolone: ".implode(', ', self::ALLOWED_CONTEXT_MODES)
                );
            }
        });
    }

    /**
     * Wirtualne pole kompatybilności wstecznej dla internet_enabled.
     */
    protected function internetEnabled(): Attribute
    {
        return Attribute::make(
            get: fn (?bool $value, array $attributes) => ($attributes['internet_mode'] ?? self::INTERNET_OFF) !== self::INTERNET_OFF,
            set: fn (bool $value) => [
                'internet_mode' => $value
                    ? (($this->attributes['internet_mode'] ?? self::INTERNET_OFF) === self::INTERNET_OPEN ? self::INTERNET_OPEN : self::INTERNET_ALLOWLIST)
                    : self::INTERNET_OFF,
            ]
        );
    }

    public function isStateless(): bool
    {
        return $this->context_mode === self::CONTEXT_STATELESS;
    }

    public function hasInternetAccess(): bool
    {
        return $this->internet_mode !== self::INTERNET_OFF;
    }

    public function isOpenInternet(): bool
    {
        return $this->internet_mode === self::INTERNET_OPEN;
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(LlmAccountPool::class, 'pool_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(AgentSkill::class, 'agent_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'agent_id');
    }
}
