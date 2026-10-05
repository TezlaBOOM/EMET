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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('runtime_type', 30)->default('native'); // native, hermes, openclaw, claude_code, codex
            $table->unsignedBigInteger('instance_id')->nullable()->index();
            $table->foreignId('pool_id')->nullable()->constrained('llm_account_pools')->nullOnDelete();
            $table->string('primary_model', 100)->default('gpt-4o');
            $table->text('system_prompt')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedBigInteger('memory_collection_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('driver', 100); // web_search, bash_executor, file_editor, etc.
            $table->json('config')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('title', 255);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role', 30); // user, assistant, system, tool
            $table->longText('content');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('agent_skills');
        Schema::dropIfExists('agents');
    }
};
