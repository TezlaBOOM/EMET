<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_triggers', function (Blueprint $table) {
            if (! Schema::hasColumn('scenario_triggers', 'name')) {
                $table->string('name', 150)->default('Główny harmonogram')->after('scenario_id');
            }
            if (! Schema::hasColumn('scenario_triggers', 'schedule_type')) {
                $table->string('schedule_type', 30)->default('cron')->after('type');
            }
            if (! Schema::hasColumn('scenario_triggers', 'schedule')) {
                $table->json('schedule')->nullable()->after('schedule_type');
            }
            if (! Schema::hasColumn('scenario_triggers', 'timezone')) {
                $table->string('timezone', 50)->default('Europe/Warsaw')->after('schedule');
            }
            if (! Schema::hasColumn('scenario_triggers', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('scenario_triggers', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('scenario_triggers', 'max_runs')) {
                $table->unsignedInteger('max_runs')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('scenario_triggers', 'runs_count')) {
                $table->unsignedInteger('runs_count')->default(0)->after('max_runs');
            }
            if (! Schema::hasColumn('scenario_triggers', 'input')) {
                $table->json('input')->nullable()->after('runs_count');
            }
            if (! Schema::hasColumn('scenario_triggers', 'overlap_policy')) {
                $table->string('overlap_policy', 20)->default('skip')->after('input');
            }
            if (! Schema::hasColumn('scenario_triggers', 'misfire_policy')) {
                $table->string('misfire_policy', 20)->default('skip')->after('overlap_policy');
            }
            if (! Schema::hasColumn('scenario_triggers', 'next_run_at')) {
                $table->timestamp('next_run_at')->nullable()->after('misfire_policy');
            }
            if (! Schema::hasColumn('scenario_triggers', 'last_run_at')) {
                $table->timestamp('last_run_at')->nullable()->after('next_run_at');
            }
            if (! Schema::hasColumn('scenario_triggers', 'last_run_id')) {
                $table->string('last_run_id', 36)->nullable()->after('last_run_at');
            }
            if (! Schema::hasColumn('scenario_triggers', 'consecutive_failures')) {
                $table->unsignedInteger('consecutive_failures')->default(0)->after('last_run_id');
            }
            if (! Schema::hasColumn('scenario_triggers', 'last_error')) {
                $table->text('last_error')->nullable()->after('consecutive_failures');
            }
            if (! Schema::hasColumn('scenario_triggers', 'state')) {
                $table->string('state', 20)->default('active')->after('last_error');
            }
        });

        Schema::table('scenario_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('scenario_runs', 'trigger_id')) {
                $table->foreignId('trigger_id')->nullable()->constrained('scenario_triggers')->nullOnDelete();
            }
            if (! Schema::hasColumn('scenario_runs', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable();
            }
            if (! Schema::hasColumn('scenario_runs', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('scenario_runs', function (Blueprint $table) {
            if (Schema::hasColumn('scenario_runs', 'trigger_id')) {
                $table->dropForeign(['trigger_id']);
                $table->dropColumn(['trigger_id', 'scheduled_for', 'idempotency_key']);
            }
        });

        Schema::table('scenario_triggers', function (Blueprint $table) {
            if (Schema::hasColumn('scenario_triggers', 'schedule_type')) {
                $table->dropColumn([
                    'name',
                    'schedule_type',
                    'schedule',
                    'timezone',
                    'starts_at',
                    'ends_at',
                    'max_runs',
                    'runs_count',
                    'input',
                    'overlap_policy',
                    'misfire_policy',
                    'next_run_at',
                    'last_run_at',
                    'last_run_id',
                    'consecutive_failures',
                    'last_error',
                    'state',
                ]);
            }
        });
    }
};
