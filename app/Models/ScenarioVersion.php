<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id',
        'version',
        'graph',
        'draft',
        'changelog',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'graph' => 'array',
        'draft' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScenarioRun::class, 'version_id');
    }
}
