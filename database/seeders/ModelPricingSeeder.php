<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ModelPricing;
use Illuminate\Database\Seeder;

class ModelPricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pricings = [
            ['model_pattern' => 'gemini-2.5-flash*', 'input_cost_per_million' => 0.0750, 'output_cost_per_million' => 0.3000],
            ['model_pattern' => 'gemini-1.5-flash*', 'input_cost_per_million' => 0.0750, 'output_cost_per_million' => 0.3000],
            ['model_pattern' => 'gemini-1.5-pro*', 'input_cost_per_million' => 1.2500, 'output_cost_per_million' => 5.0000],
            ['model_pattern' => 'claude-3-5-sonnet*', 'input_cost_per_million' => 3.0000, 'output_cost_per_million' => 15.0000],
            ['model_pattern' => 'gpt-4o*', 'input_cost_per_million' => 2.5000, 'output_cost_per_million' => 10.0000],
            ['model_pattern' => 'gpt-4o-mini*', 'input_cost_per_million' => 0.1500, 'output_cost_per_million' => 0.6000],
            ['model_pattern' => 'text-embedding-3*', 'input_cost_per_million' => 0.0200, 'output_cost_per_million' => 0.0000],
            ['model_pattern' => 'ollama*', 'input_cost_per_million' => 0.0000, 'output_cost_per_million' => 0.0000],
        ];

        foreach ($pricings as $pricing) {
            ModelPricing::updateOrCreate(
                ['model_pattern' => $pricing['model_pattern']],
                $pricing
            );
        }
    }
}
