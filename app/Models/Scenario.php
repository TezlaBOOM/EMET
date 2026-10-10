<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Scenario extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'current_version_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Scenario $scenario) {
            if (empty($scenario->slug)) {
                $scenario->slug = Str::slug($scenario->name);
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScenarioVersion::class, 'scenario_id')->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ScenarioVersion::class, 'current_version_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ScenarioTrigger::class, 'scenario_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScenarioRun::class, 'scenario_id')->latest();
    }
}
