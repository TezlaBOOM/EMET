<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->mediumText('readme_md')->nullable();
            $table->string('type', 30)->default('tool'); // tool, mcp, prompt, workflow, package
            $table->string('status', 30)->default('active'); // draft, pending_review, active, deprecated
            $table->string('author', 100)->nullable();
            $table->json('tags')->nullable();
            $table->string('source_ref', 255)->nullable();
            $table->json('requires')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('skill_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('version', 30);
            $table->json('definition')->nullable();
            $table->string('package_path', 255)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['skill_id', 'version']);
        });

        Schema::table('agent_skills', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->after('agent_id')->constrained('skills')->nullOnDelete();
            $table->unsignedBigInteger('skill_version_id')->nullable()->after('skill_id')->index();
            $table->integer('sort')->default(0)->after('is_enabled');
        });

        Schema::create('skill_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedBigInteger('version_id')->nullable()->index();
            $table->unsignedBigInteger('agent_run_id')->nullable()->index();
            $table->unsignedBigInteger('scenario_run_step_id')->nullable()->index();
            $table->json('input_summary')->nullable();
            $table->string('status', 30)->default('running'); // running, success, failed, timeout
            $table->integer('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_runs');

        Schema::table('agent_skills', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
            $table->dropColumn(['skill_id', 'skill_version_id', 'sort']);
        });

        Schema::dropIfExists('skill_versions');
        Schema::dropIfExists('skills');
    }
};
