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
        Schema::create('integration_instances', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // hermes, openclaw, claude_code, codex
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('mode', 30)->default('systemd'); // systemd, docker
            $table->string('status', 30)->default('provisioning'); // running, stopped, provisioning, error, degraded
            $table->string('endpoint_url', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('provisioning_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('instance_id')->constrained('integration_instances')->cascadeOnDelete();
            $table->string('action', 50)->default('provision'); // provision, start, stop, restart, rollback, destroy
            $table->string('status', 30)->default('queued'); // queued, running, completed, failed, rolled_back
            $table->longText('logs')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provisioning_jobs');
        Schema::dropIfExists('integration_instances');
    }
};
