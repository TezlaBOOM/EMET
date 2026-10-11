<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            if (! Schema::hasColumn('scenarios', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('scenarios', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            if (Schema::hasColumn('scenarios', 'locked_by')) {
                $table->dropForeign(['locked_by']);
                $table->dropColumn(['locked_by', 'locked_at']);
            }
        });
    }
};
