<?php

use App\Models\LlmProvider;
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
        if (! Schema::hasColumn('llm_accounts', 'custom_base_url')) {
            Schema::table('llm_accounts', function (Blueprint $table) {
                $table->string('custom_base_url', 255)->nullable()->after('organization_id');
            });
        }

        LlmProvider::firstOrCreate(
            ['slug' => 'lmstudio'],
            [
                'name' => 'LM Studio (Lokalny)',
                'driver' => 'lmstudio',
                'base_url' => 'http://localhost:1234/v1',
                'default_model' => 'local-model',
                'is_active' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('llm_accounts', 'custom_base_url')) {
            Schema::table('llm_accounts', function (Blueprint $table) {
                $table->dropColumn('custom_base_url');
            });
        }

        LlmProvider::where('slug', 'lmstudio')->delete();
    }
};
