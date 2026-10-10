<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32); // export, import
            $table->string('mode', 32)->default('merge'); // merge, overwrite, new_only
            $table->string('status', 32)->default('pending'); // pending, processing, completed, failed, rolled_back
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('file_hash', 64)->nullable(); // sha256
            $table->json('sections')->nullable();
            $table->json('stats')->nullable();
            $table->json('diff_report')->nullable();
            $table->boolean('has_secrets')->default(false);
            $table->string('backup_path', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_transfers');
    }
};
