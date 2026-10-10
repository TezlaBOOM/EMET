<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoConfigProfile extends Model
{
    use HasFactory;

    protected $table = 'autoconfig_profiles';

    protected $fillable = [
        'name',
        'slug',
        'adapter_type',
        'target_model',
        'steps',
        'is_active',
    ];

    protected $casts = [
        'steps' => 'array',
        'is_active' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(ContainerConfigRun::class, 'profile_id');
    }
}
