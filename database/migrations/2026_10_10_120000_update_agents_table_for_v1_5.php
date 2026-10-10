<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('internet_mode', 20)->default('off')->after('is_active');
            $table->string('context_mode', 20)->default('stateful')->after('internet_mode');
            $table->unsignedInteger('context_window_messages')->nullable()->after('context_mode');
        });

        // Backfill z internet_enabled jeżeli taka kolumna istnieje
        if (Schema::hasColumn('agents', 'internet_enabled')) {
            DB::table('agents')
                ->where('internet_enabled', true)
                ->update(['internet_mode' => 'allowlist']);
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'internet_mode',
                'context_mode',
                'context_window_messages',
            ]);
        });
    }
};
