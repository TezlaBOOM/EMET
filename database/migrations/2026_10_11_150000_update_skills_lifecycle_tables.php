<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (! Schema::hasColumn('skills', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('skills', 'overrides')) {
                $table->json('overrides')->nullable();
            }
            if (! Schema::hasColumn('skills', 'locked_by_module')) {
                $table->boolean('locked_by_module')->default(false);
            }
        });

        Schema::table('skill_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('skill_runs', 'definition_snapshot')) {
                $table->json('definition_snapshot')->nullable()->after('input_summary');
            }
        });

        Schema::table('skill_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('skill_versions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('skill_versions', function (Blueprint $table) {
            if (Schema::hasColumn('skill_versions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('skill_runs', function (Blueprint $table) {
            if (Schema::hasColumn('skill_runs', 'definition_snapshot')) {
                $table->dropColumn('definition_snapshot');
            }
        });

        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn(['deleted_by', 'overrides', 'locked_by_module']);
            }
        });
    }
};
