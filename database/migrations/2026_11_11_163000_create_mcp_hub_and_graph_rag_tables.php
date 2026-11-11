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
        if (! Schema::hasTable('mcp_servers')) {
            Schema::create('mcp_servers', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->string('transport', 30)->default('streamable_http'); // streamable_http, http_sse, stdio
                $table->string('endpoint_url', 255)->nullable();
                $table->string('command', 255)->nullable();
                $table->text('auth_token')->nullable();
                $table->string('status', 30)->default('active'); // active, disconnected, error
                $table->json('discovered_tools')->nullable();
                $table->unsignedInteger('synced_skills_count')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('memory_entities')) {
            Schema::create('memory_entities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('collection_id')->constrained('memory_collections')->cascadeOnDelete();
                $table->uuid('chunk_id')->nullable()->index();
                $table->string('name', 150);
                $table->string('normalized_name', 150)->index();
                $table->string('entity_type', 50)->default('concept')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['collection_id', 'normalized_name'], 'mem_ent_col_norm_idx');
            });
        }

        Schema::dropIfExists('memory_relations');
        Schema::create('memory_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('memory_collections')->cascadeOnDelete();
            $table->foreignId('source_entity_id')->constrained('memory_entities')->cascadeOnDelete();
            $table->foreignId('target_entity_id')->constrained('memory_entities')->cascadeOnDelete();
            $table->string('relation_type', 60)->default('co_occurs_with');
            $table->float('weight')->default(1.0);
            $table->uuid('chunk_id')->nullable()->index();
            $table->timestamps();

            $table->index(['collection_id', 'source_entity_id', 'target_entity_id'], 'mem_rel_col_src_tgt_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_relations');
        Schema::dropIfExists('memory_entities');
        Schema::dropIfExists('mcp_servers');
    }
};
