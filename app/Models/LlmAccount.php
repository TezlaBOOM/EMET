<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmAccount extends Model
{
    protected $fillable = [
        'provider_id',
        'name',
        'api_key',
        'api_secret',
        'organization_id',
        'weight',
        'rpm_limit',
        'tpm_limit',
        'current_status',
        'cooldown_until',
        'last_error_message',
        'last_tested_at',
        'last_used_at',
    ];

    /**
     * Szyfrowanie klucza API i sekretu przez mechanizm 'encrypted' w bazie danych
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'cooldown_until' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_used_at' => 'datetime',
            'weight' => 'integer',
            'rpm_limit' => 'integer',
            'tpm_limit' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }

    /**
     * Czy konto jest obecnie gotowe do przyjęcia zapytania
     */
    public function isAvailable(): bool
    {
        if ($this->current_status === 'error') {
            return false;
        }

        if ($this->current_status === 'cooldown' && $this->cooldown_until && $this->cooldown_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Ustawia konto w stan cooldown po błędzie 429
     */
    public function setCooldown(int $seconds = 60, ?string $errorMessage = null): void
    {
        $this->update([
            'current_status' => 'cooldown',
            'cooldown_until' => now()->addSeconds($seconds),
            'last_error_message' => $errorMessage,
        ]);
    }

    /**
     * Przywraca konto do stanu aktywnego
     */
    public function markAsActive(): void
    {
        $this->update([
            'current_status' => 'active',
            'cooldown_until' => null,
            'last_error_message' => null,
            'last_tested_at' => now(),
        ]);
    }
}
