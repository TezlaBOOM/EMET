<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPRECATED = 'deprecated';

    public const TYPE_TOOL = 'tool';

    public const TYPE_MCP = 'mcp';

    public const TYPE_PROMPT = 'prompt';

    public const TYPE_WORKFLOW = 'workflow';

    public const TYPE_PACKAGE = 'package';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'readme_md',
        'type',
        'status',
        'author',
        'tags',
        'source_ref',
        'requires',
        'current_version_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'requires' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Skill $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isDeprecated(): bool
    {
        return $this->status === self::STATUS_DEPRECATED;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SkillVersion::class, 'skill_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SkillVersion::class, 'current_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SkillRun::class, 'skill_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_skills', 'skill_id', 'agent_id')
            ->withPivot(['id', 'skill_version_id', 'is_enabled', 'sort'])
            ->withTimestamps();
    }
}
