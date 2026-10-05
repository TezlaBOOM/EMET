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
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('run_id', 100)->nullable()->index();
            $table->string('type', 50)->index(); // run.started, tool.called, memory.read, memory.write, llm.call, run.finished, error
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('telemetry_rollups', function (Blueprint $table) {
            $table->id();
            $table->string('period_type', 20)->index(); // hourly, daily
            $table->timestamp('period_start')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->unsignedBigInteger('provider_id')->nullable()->index();
            $table->string('model', 100)->nullable()->index();
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedBigInteger('total_prompt_tokens')->default(0);
            $table->unsignedBigInteger('total_completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedInteger('avg_ttft_ms')->default(0);
            $table->unsignedInteger('avg_duration_ms')->default(0);
            $table->unsignedInteger('p95_duration_ms')->default(0);
            $table->decimal('total_cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->unique(['period_type', 'period_start', 'agent_id', 'provider_id', 'model'], 'telemetry_rollups_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_rollups');
        Schema::dropIfExists('telemetry_events');
    }
};
