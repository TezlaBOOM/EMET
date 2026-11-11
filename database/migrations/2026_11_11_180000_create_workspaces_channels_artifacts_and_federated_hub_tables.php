<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // #9 Wielodostępność Przestrzeni Roboczych i Budżety Zespołowe (Workspaces & FinOps Quotas)
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->decimal('daily_budget_usd', 10, 4)->default(10.0000);
            $table->decimal('monthly_budget_usd', 10, 4)->default(200.0000);
            $table->unsignedBigInteger('daily_token_limit')->default(500000);
            $table->decimal('spent_today_usd', 10, 6)->default(0.000000);
            $table->decimal('spent_month_usd', 10, 6)->default(0.000000);
            $table->unsignedBigInteger('tokens_used_today')->default(0);
            $table->unsignedTinyInteger('alert_threshold_pct')->default(80);
            $table->boolean('fallback_to_local_on_exceed')->default(true);
            $table->string('fallback_model', 100)->default('ollama/llama3.2');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // #10 Dwukierunkowe Kanały Komunikacyjne (External Channels & ChatOps Bots)
        Schema::create('external_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('platform', 40)->index(); // telegram, slack, discord, teams, email
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('scenario_id')->nullable()->constrained('scenarios')->nullOnDelete();
            $table->text('webhook_secret')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('messages_handled_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
        });

        // #7 Multimodalność i Panel Artefaktów na Żywo (Live Preview Canvas)
        Schema::create('chat_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->string('conversation_id', 64)->nullable()->index();
            $table->string('message_id', 64)->nullable()->index();
            $table->string('title', 180);
            $table->string('artifact_type', 40)->default('code'); // html, mermaid, svg, chart, json, code, markdown
            $table->string('language', 40)->nullable();
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        // #12 Sfederowany Katalog Skilli i Szablonów z Podpisami Kryptograficznymi Ed25519
        Schema::create('federated_registries', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('registry_url', 255);
            $table->string('public_key_ed25519', 128);
            $table->boolean('is_trusted')->default(true);
            $table->json('cached_catalog')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federated_registries');
        Schema::dropIfExists('chat_artifacts');
        Schema::dropIfExists('external_channels');
        Schema::dropIfExists('workspaces');
    }
};
