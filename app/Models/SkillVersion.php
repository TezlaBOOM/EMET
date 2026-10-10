<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill_id',
        'version',
        'definition',
        'package_path',
        'checksum',
        'changelog',
        'created_by',
    ];

    protected $casts = [
        'definition' => 'array',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SkillRun::class, 'version_id');
    }
}
