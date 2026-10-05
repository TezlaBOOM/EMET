<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPricing extends Model
{
    protected $table = 'model_pricing';

    protected $fillable = [
        'model_pattern',
        'input_cost_per_million',
        'output_cost_per_million',
    ];

    protected function casts(): array
    {
        return [
            'input_cost_per_million' => 'float',
            'output_cost_per_million' => 'float',
        ];
    }

    /**
     * Oblicza szacunkowy koszt wywołania w USD na podstawie liczby tokenów
     */
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricings = static::all();

        foreach ($pricings as $pricing) {
            if (fnmatch($pricing->model_pattern, $model)) {
                $inputCost = ($promptTokens / 1_000_000) * $pricing->input_cost_per_million;
                $outputCost = ($completionTokens / 1_000_000) * $pricing->output_cost_per_million;

                return round($inputCost + $outputCost, 6);
            }
        }

        return 0.0;
    }
}
