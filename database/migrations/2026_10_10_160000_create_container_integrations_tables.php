<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_id', 128)->unique();
            $table->string('name', 128);
            $table->string('image', 255);
            $table->string('status', 64)->default('unknown');
            $table->string('detected_type', 64)->default('unknown');
            $table->string('adoption_mode', 32)->default('none'); // none, observe, configure, managed
            $table->string('adapter_type', 64)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('network', 128)->nullable();
            $table->json('ports')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('last_inspected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('autoconfig_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('slug', 128)->unique();
            $table->string('adapter_type', 64);
            $table->string('target_model', 128)->nullable();
            $table->json('steps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('container_config_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('container_id', 128);
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('status', 32)->default('pending'); // pending, running, completed, failed, rolled_back
            $table->json('diff_before')->nullable();
            $table->json('diff_after')->nullable();
            $table->longText('logs')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('autoconfig_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_config_runs');
        Schema::dropIfExists('autoconfig_profiles');
        Schema::dropIfExists('integration_containers');
    }
};
