<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class DefaultProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Google Gemini',
                'slug' => 'gemini',
                'driver' => 'gemini',
                'base_url' => null,
                'default_model' => 'gemini-1.5-flash',
                'is_active' => true,
            ],
            [
                'name' => 'OpenAI',
                'slug' => 'openai',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'default_model' => 'gpt-4o',
                'is_active' => true,
            ],
            [
                'name' => 'Anthropic Claude',
                'slug' => 'anthropic',
                'driver' => 'anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'default_model' => 'claude-3-5-sonnet-20241022',
                'is_active' => true,
            ],
            [
                'name' => 'Ollama (Lokalny)',
                'slug' => 'ollama',
                'driver' => 'ollama',
                'base_url' => 'http://localhost:11434',
                'default_model' => 'llama3.2',
                'is_active' => true,
            ],
            [
                'name' => 'OpenRouter',
                'slug' => 'openrouter',
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'default_model' => 'auto',
                'is_active' => true,
            ],
        ];

        foreach ($providers as $providerData) {
            LlmProvider::updateOrCreate(
                ['slug' => $providerData['slug']],
                $providerData
            );
        }
    }
}
