<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IntegrationInstance extends Model
{
    protected $fillable = [
        'type',
        'name',
        'slug',
        'mode',
        'status',
        'endpoint_url',
        'port',
        'config',
        'health_checked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'config' => 'array',
            'health_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntegrationInstance $instance) {
            if (empty($instance->slug)) {
                $instance->slug = Str::slug($instance->name);
            }
        });
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ProvisioningJob::class, 'instance_id');
    }

    public function isHealthy(): bool
    {
        return $this->status === 'running';
    }
}
