<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentEvalSuite extends Model
{
    protected $table = 'agent_eval_suites';

    protected static function booted(): void
    {
        static::creating(function (self $suite): void {
            if (empty($suite->slug)) {
                $suite->slug = Str::slug($suite->name).'-'.Str::lower(Str::random(4));
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'agent_id',
        'description',
        'test_cases',
        'pass_threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'test_cases' => 'array',
            'pass_threshold' => 'float',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentEvalRun::class, 'suite_id');
    }
}
