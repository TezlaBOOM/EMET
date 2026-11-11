<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FederatedRegistry extends Model
{
    protected $table = 'federated_registries';

    protected $fillable = [
        'name',
        'slug',
        'registry_url',
        'public_key_ed25519',
        'is_trusted',
        'cached_catalog',
        'last_synced_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $registry): void {
            if (empty($registry->slug)) {
                $registry->slug = Str::slug($registry->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'cached_catalog' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
