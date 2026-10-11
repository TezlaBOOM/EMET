<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelPricing extends Model
{
    protected $table = 'model_pricing';

    protected $fillable = [
        'account_id',
        'model_pattern',
        'input_cost_per_million',
        'output_cost_per_million',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'input_cost_per_million' => 'float',
            'output_cost_per_million' => 'float',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LlmAccount::class, 'account_id');
    }

    /**
     * Oblicza szacunkowy koszt wywołania w USD na podstawie liczby tokenów
     */
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens, ?int $accountId = null): float
    {
        // 1. Sprawdź najpierw cennik przypisany do konkretnego konta (jeśli podano)
        if ($accountId !== null) {
            $accountPricings = static::where('account_id', $accountId)->get();
            foreach ($accountPricings as $pricing) {
                if (fnmatch($pricing->model_pattern, $model)) {
                    $inputCost = ($promptTokens / 1_000_000) * $pricing->input_cost_per_million;
                    $outputCost = ($completionTokens / 1_000_000) * $pricing->output_cost_per_million;

                    return round($inputCost + $outputCost, 6);
                }
            }
        }

        // 2. Jeśli brak cennika per konto, użyj globalnego cennika (account_id = null)
        $globalPricings = static::whereNull('account_id')->get();
        foreach ($globalPricings as $pricing) {
            if (fnmatch($pricing->model_pattern, $model)) {
                $inputCost = ($promptTokens / 1_000_000) * $pricing->input_cost_per_million;
                $outputCost = ($completionTokens / 1_000_000) * $pricing->output_cost_per_million;

                return round($inputCost + $outputCost, 6);
            }
        }

        return 0.0;
    }
}
