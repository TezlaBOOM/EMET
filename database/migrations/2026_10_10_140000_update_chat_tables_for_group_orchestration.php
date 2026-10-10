<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->change();
            $table->string('mode', 20)->default('single')->after('title'); // single, group
            $table->string('orchestration', 30)->default('mention')->after('mode'); // mention, broadcast, round_robin, moderator
            $table->foreignId('lead_agent_id')->nullable()->after('orchestration')->constrained('agents')->nullOnDelete();
            $table->foreignId('moderator_agent_id')->nullable()->after('lead_agent_id')->constrained('agents')->nullOnDelete();
            $table->unsignedInteger('max_turns')->default(20)->after('moderator_agent_id');
            $table->unsignedInteger('max_rounds')->default(10)->after('max_turns');
            $table->json('limits')->nullable()->after('max_rounds');
            $table->json('settings')->nullable()->after('limits');
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // member, lead, moderator
            $table->string('join_context', 20)->default('full'); // full, summary, last_n, none
            $table->unsignedInteger('join_context_n')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['conversation_id', 'agent_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->after('conversation_id')->index();
            $table->string('turn_id', 36)->nullable()->after('participant_id')->index();
            $table->unsignedInteger('round')->default(1)->after('turn_id');
            $table->uuid('reply_to_message_id')->nullable()->after('round')->index();
            $table->string('kind', 20)->default('user')->after('reply_to_message_id'); // user, agent, system
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'participant_id',
                'turn_id',
                'round',
                'reply_to_message_id',
                'kind',
            ]);
        });

        Schema::dropIfExists('conversation_participants');

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
            $table->dropForeign(['lead_agent_id']);
            $table->dropForeign(['moderator_agent_id']);
            $table->dropColumn([
                'mode',
                'orchestration',
                'lead_agent_id',
                'moderator_agent_id',
                'max_turns',
                'max_rounds',
                'limits',
                'settings',
            ]);
        });
    }
};
