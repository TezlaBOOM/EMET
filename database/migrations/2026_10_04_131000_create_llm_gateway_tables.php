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
        Schema::create('llm_account_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('strategy', 50)->default('weighted'); // round_robin, weighted, least_used, priority_fallback
            $table->json('allowed_models')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('llm_account_pool_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('llm_account_pools')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('llm_accounts')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('custom_weight')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'account_id']);
        });

        Schema::create('model_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('model_pattern', 100)->unique();
            $table->decimal('input_cost_per_million', 10, 4)->default(0);
            $table->decimal('output_cost_per_million', 10, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('llm_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->foreignId('account_id')->nullable()->constrained('llm_accounts')->nullOnDelete();
            $table->foreignId('provider_id')->constrained('llm_providers')->cascadeOnDelete();
            $table->string('model', 100)->index();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('ttft_ms')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->string('status', 30)->default('success'); // success, failed, timeout, rate_limited
            $table->text('prompt_preview')->nullable();
            $table->text('response_preview')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_calls');
        Schema::dropIfExists('model_pricing');
        Schema::dropIfExists('llm_account_pool_members');
        Schema::dropIfExists('llm_account_pools');
    }
};
