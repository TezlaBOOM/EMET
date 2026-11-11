<?php

declare(strict_types=1);

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
        Schema::create('llm_semantic_cache_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('cache_key', 64)->index();
            $table->string('model', 100)->index();
            $table->string('system_prompt_hash', 64)->index();
            $table->text('prompt_text');
            $table->json('prompt_embedding');
            $table->longText('response_content');
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('hit_count')->default(0);
            $table->unsignedBigInteger('saved_tokens')->default(0);
            $table->decimal('saved_cost_usd', 10, 6)->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('llm_guardrail_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 50)->index();
            $table->string('severity', 20)->default('medium');
            $table->string('action_taken', 30);
            $table->json('matched_rules')->nullable();
            $table->unsignedInteger('masked_items_count')->default(0);
            $table->string('model', 100)->nullable();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->text('prompt_excerpt')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_guardrail_events');
        Schema::dropIfExists('llm_semantic_cache_entries');
    }
};

