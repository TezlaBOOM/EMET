<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationContainer extends Model
{
    use HasFactory;

    protected $table = 'integration_containers';

    protected $fillable = [
        'container_id',
        'name',
        'image',
        'status',
        'detected_type',
        'adoption_mode',
        'adapter_type',
        'ip_address',
        'network',
        'ports',
        'config',
        'last_inspected_at',
    ];

    protected $casts = [
        'ports' => 'array',
        'config' => 'array',
        'last_inspected_at' => 'datetime',
    ];

    public function isAdopted(): bool
    {
        return in_array($this->adoption_mode, ['observe', 'configure', 'managed'], true);
    }

    public function isManaged(): bool
    {
        return $this->adoption_mode === 'managed';
    }

    public function configRuns(): HasMany
    {
        return $this->hasMany(ContainerConfigRun::class, 'container_id', 'container_id');
    }
}
