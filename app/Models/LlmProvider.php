<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'base_url',
        'is_active',
        'default_model',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(LlmAccount::class, 'provider_id');
    }

    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('current_status', 'active');
    }
}
