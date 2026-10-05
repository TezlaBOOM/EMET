<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmAccountPoolMember extends Model
{
    protected $fillable = [
        'pool_id',
        'account_id',
        'priority',
        'custom_weight',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'custom_weight' => 'integer',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(LlmAccountPool::class, 'pool_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LlmAccount::class, 'account_id');
    }
}
