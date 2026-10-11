<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('models')) {
            Schema::create('models', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_id')->constrained('llm_providers')->cascadeOnDelete();
                $table->string('model_key', 150);
                $table->string('display_name', 150);
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true);
                $table->json('capabilities')->nullable();
                $table->unsignedInteger('context_window')->default(4096);
                $table->unsignedInteger('max_output_tokens')->nullable();
                $table->json('parameters')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->json('overrides')->nullable();
                $table->json('tags')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->unique(['provider_id', 'model_key']);
            });
        }

        if (Schema::hasTable('model_pricing') && ! Schema::hasColumn('model_pricing', 'account_id')) {
            Schema::table('model_pricing', function (Blueprint $table) {
                $table->foreignId('account_id')->nullable()->constrained('llm_accounts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('model_pricing') && Schema::hasColumn('model_pricing', 'account_id')) {
            Schema::table('model_pricing', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }

        Schema::dropIfExists('models');
    }
};
