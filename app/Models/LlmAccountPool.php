<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmAccountPool extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'strategy',
        'allowed_models',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_models' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(LlmAccountPoolMember::class, 'pool_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(LlmAccount::class, 'llm_account_pool_members', 'pool_id', 'account_id')
            ->withPivot(['priority', 'custom_weight'])
            ->withTimestamps();
    }
}
