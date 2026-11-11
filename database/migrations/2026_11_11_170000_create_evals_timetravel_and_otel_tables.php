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
        // 1. Podsystem Ewaluacji Jakości i Testów A/B Agentów (#4)
        Schema::create('agent_eval_suites', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('test_cases');
            $table->float('pass_threshold')->default(0.80);
            $table->timestamps();
        });

        Schema::create('agent_eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('suite_id')->constrained('agent_eval_suites')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('model_a', 100);
            $table->string('model_b', 100)->nullable();
            $table->string('status', 30)->default('passed'); // running, passed, failed
            $table->float('score_a')->default(0.0);
            $table->float('score_b')->nullable();
            $table->unsignedInteger('avg_latency_ms_a')->default(0);
            $table->unsignedInteger('avg_latency_ms_b')->nullable();
            $table->decimal('total_cost_usd_a', 10, 6)->default(0);
            $table->decimal('total_cost_usd_b', 10, 6)->nullable();
            $table->string('winner_model', 100)->nullable();
            $table->json('case_results')->nullable();
            $table->timestamp('created_at')->index();
        });

        // 2. Zaawansowany Debugger Scenariuszy Time-Travel & Forking Stanu (#5)
        Schema::table('scenario_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_run_id')->nullable()->index();
            $table->string('forked_from_node_id', 64)->nullable();
            $table->string('trace_id', 64)->nullable()->index();
            $table->json('state_snapshots')->nullable();
        });

        Schema::table('scenario_run_steps', function (Blueprint $table): void {
            $table->json('state_snapshot_before')->nullable();
            $table->json('state_snapshot_after')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
        });

        // 3. Rozproszony Tracing OpenTelemetry i Analityka Przyczyn Błędów (#11)
        Schema::create('telemetry_spans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('trace_id', 64)->index();
            $table->string('span_id', 32)->unique();
            $table->string('parent_span_id', 32)->nullable()->index();
            $table->string('name', 150);
            $table->string('service_name', 60)->index();
            $table->string('span_kind', 30)->default('INTERNAL');
            $table->string('status', 20)->default('OK')->index();
            $table->unsignedBigInteger('start_time_us')->default(0);
            $table->unsignedBigInteger('end_time_us')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('attributes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_spans');

        Schema::table('scenario_run_steps', function (Blueprint $table): void {
            $table->dropColumn(['state_snapshot_before', 'state_snapshot_after', 'duration_ms']);
        });

        Schema::table('scenario_runs', function (Blueprint $table): void {
            $table->dropColumn(['parent_run_id', 'forked_from_node_id', 'trace_id', 'state_snapshots']);
        });

        Schema::dropIfExists('agent_eval_runs');
        Schema::dropIfExists('agent_eval_suites');
    }
};

