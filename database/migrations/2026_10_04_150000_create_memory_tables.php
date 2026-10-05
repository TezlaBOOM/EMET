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
        Schema::create('memory_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('vector_store', 30)->default('qdrant'); // qdrant, pgvector
            $table->foreignId('embedding_provider_id')->constrained('llm_providers')->cascadeOnDelete();
            $table->string('embedding_model', 100)->default('text-embedding-3-small');
            $table->unsignedInteger('dimensions')->default(1536);
            $table->string('distance_metric', 20)->default('cosine'); // cosine, euclidean, dot
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('memory_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('collection_id')->constrained('memory_collections')->cascadeOnDelete();
            $table->string('title', 255);
            $table->longText('content');
            $table->string('vector_point_id', 100)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_chunks');
        Schema::dropIfExists('memory_collections');
    }
};
