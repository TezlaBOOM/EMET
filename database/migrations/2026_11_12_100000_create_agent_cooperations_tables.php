<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_cooperations')) {
            Schema::create('agent_cooperations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('topology')->default('round_table'); // round_table, pipeline, supervisor, debate_consensus
                $table->foreignId('coordinator_agent_id')->nullable()->constrained('agents')->nullOnDelete();
                $table->json('participant_agent_ids');
                $table->json('participant_roles')->nullable();

                // Strażnik Przegrzania (Thermal & Loop Overheat Guard)
                $table->boolean('overheat_guard_enabled')->default(true);
                $table->unsignedInteger('max_rounds')->default(4);
                $table->unsignedInteger('max_tokens_budget')->default(12000);
                $table->decimal('max_temperature_score', 5, 2)->default(85.00);
                $table->unsignedInteger('cooldown_seconds')->default(30);
                $table->decimal('repetition_threshold', 4, 2)->default(0.78);
                $table->boolean('auto_synthesize_on_overheat')->default(true);

                // Telemetria Termiczna i Stan Sesji
                $table->decimal('current_temperature', 5, 2)->default(22.00);
                $table->string('thermal_status')->default('nominal'); // nominal, warm, hot, overheated
                $table->string('status')->default('idle'); // idle, running, completed, cooled_down, overheated
                $table->unsignedInteger('total_tokens_used')->default(0);
                $table->unsignedInteger('total_turns_executed')->default(0);
                $table->unsignedInteger('context_reloads_count')->default(0);
                $table->text('last_task_prompt')->nullable();
                $table->longText('preserved_context')->nullable();
                $table->longText('final_synthesis')->nullable();
                $table->json('discussion_log')->nullable();
                $table->json('overheat_events')->nullable();
                $table->timestamp('cooled_down_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_cooperations');
    }
};
