<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft'); // draft, published, archived
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scenario_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('graph');
            $table->boolean('draft')->default(false);
            $table->text('changelog')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['scenario_id', 'version']);
        });

        Schema::create('scenario_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            $table->string('type', 30); // manual, cron, webhook
            $table->json('config')->nullable();
            $table->string('token_hash', 64)->nullable()->index();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scenario_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            $table->unsignedBigInteger('version_id')->nullable()->index();
            $table->string('status', 30)->default('pending'); // pending, running, paused, success, failed, cancelled
            $table->string('trigger_type', 30)->default('manual');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('vars')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 10, 6)->default(0.000000);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scenario_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('scenario_runs')->cascadeOnDelete();
            $table->string('node_id', 50)->index();
            $table->string('node_type', 30);
            $table->string('status', 30)->default('pending'); // pending, running, waiting, success, failed, skipped, retrying
            $table->unsignedInteger('attempt')->default(1);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedBigInteger('agent_run_id')->nullable()->index();
            $table->unsignedBigInteger('skill_run_id')->nullable()->index();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 10, 6)->default(0.000000);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
        });

        if (Schema::hasTable('telemetry_events')) {
            Schema::table('telemetry_events', function (Blueprint $table) {
                $table->unsignedBigInteger('scenario_run_id')->nullable()->index();
                $table->string('node_id', 50)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('telemetry_events')) {
            Schema::table('telemetry_events', function (Blueprint $table) {
                $table->dropColumn(['scenario_run_id', 'node_id']);
            });
        }

        Schema::dropIfExists('scenario_run_steps');
        Schema::dropIfExists('scenario_runs');
        Schema::dropIfExists('scenario_triggers');
        Schema::dropIfExists('scenario_versions');
        Schema::dropIfExists('scenarios');
    }
};
