<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('driver', 50); // gemini, openai, anthropic, ollama, openrouter
            $table->string('base_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('default_model', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('llm_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('llm_providers')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('api_key'); // Szyfrowane pole w modelu przez cast: 'encrypted'
            $table->text('api_secret')->nullable(); // Opcjonalny sekret szyfrowany
            $table->string('organization_id', 100)->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('rpm_limit')->nullable();
            $table->unsignedInteger('tpm_limit')->nullable();
            $table->string('current_status', 30)->default('active'); // active, cooldown, rate_limited, error
            $table->timestamp('cooldown_until')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_accounts');
        Schema::dropIfExists('llm_providers');
    }
};
