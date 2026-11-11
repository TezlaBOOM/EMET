<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Workspace extends Model
{
    protected $table = 'workspaces';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'daily_budget_usd',
        'monthly_budget_usd',
        'daily_token_limit',
        'spent_today_usd',
        'spent_month_usd',
        'tokens_used_today',
        'alert_threshold_pct',
        'fallback_to_local_on_exceed',
        'fallback_model',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $workspace): void {
            if (empty($workspace->slug)) {
                $workspace->slug = Str::slug($workspace->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_budget_usd' => 'float',
            'monthly_budget_usd' => 'float',
            'daily_token_limit' => 'integer',
            'spent_today_usd' => 'float',
            'spent_month_usd' => 'float',
            'tokens_used_today' => 'integer',
            'alert_threshold_pct' => 'integer',
            'fallback_to_local_on_exceed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
