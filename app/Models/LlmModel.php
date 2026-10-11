<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmModel extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'provider_id',
        'model_key',
        'display_name',
        'description',
        'enabled',
        'capabilities',
        'context_window',
        'max_output_tokens',
        'parameters',
        'is_manual',
        'overrides',
        'tags',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_manual' => 'boolean',
            'context_window' => 'integer',
            'max_output_tokens' => 'integer',
            'capabilities' => 'array',
            'parameters' => 'array',
            'overrides' => 'array',
            'tags' => 'array',
            'sort' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }

    public function hasCapability(string $capability): bool
    {
        $caps = $this->capabilities ?? [];

        return in_array($capability, $caps, true);
    }

    public function isOverridden(string $field): bool
    {
        $overrides = $this->overrides ?? [];

        return in_array($field, $overrides, true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
