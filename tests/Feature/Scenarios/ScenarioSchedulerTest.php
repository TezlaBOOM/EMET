<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Scenarios\ScenarioSchedulerInterface;
use App\Models\Scenario;
use App\Models\ScenarioTrigger;
use App\Models\ScenarioVersion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->scenario = Scenario::create([
            'name' => 'Scenariusz Harmonogramu',
            'slug' => 'scenariusz-harmonogramu',
            'status' => Scenario::STATUS_PUBLISHED,
            'created_by' => $this->admin->id,
        ]);

        $version = ScenarioVersion::create([
            'scenario_id' => $this->scenario->id,
            'version' => 1,
            'graph' => ['nodes' => []],
            'draft' => false,
        ]);

        $this->scenario->update(['current_version_id' => $version->id]);
    }

    public function test_scheduler_calculates_next_run_correctly_for_all_six_types(): void
    {
        /** @var ScenarioSchedulerInterface $scheduler */
        $scheduler = app(ScenarioSchedulerInterface::class);
        $base = Carbon::parse('2026-10-15 10:00:00', 'Europe/Warsaw');

        // 1. Once
        $runAt = '2026-10-20 15:30:00';
        $nextOnce = $scheduler->calculateNextRun('once', ['run_at' => $runAt], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextOnce);
        $this->assertSame('2026-10-20 15:30:00', $nextOnce->format('Y-m-d H:i:s'));

        // 2. Interval (co 15 minut)
        $nextInterval = $scheduler->calculateNextRun('interval', ['interval_minutes' => 15], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextInterval);
        $this->assertSame('2026-10-15 10:15:00', $nextInterval->format('Y-m-d H:i:s'));

        // 3. Daily (o 12:00 tego samego dnia)
        $nextDaily = $scheduler->calculateNextRun('daily', ['time' => '12:00'], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextDaily);
        $this->assertSame('2026-10-15 12:00:00', $nextDaily->format('Y-m-d H:i:s'));

        // Daily (gdy godzina już minęła, np. 08:00 -> następny dzień)
        $nextDailyPast = $scheduler->calculateNextRun('daily', ['time' => '08:00'], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextDailyPast);
        $this->assertSame('2026-10-16 08:00:00', $nextDailyPast->format('Y-m-d H:i:s'));

        // 4. Weekly
        $nextWeekly = $scheduler->calculateNextRun('weekly', ['days' => ['friday'], 'time' => '14:00'], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextWeekly);
        $this->assertSame('2026-10-16 14:00:00', $nextWeekly->format('Y-m-d H:i:s'));

        // 5. Monthly
        $nextMonthly = $scheduler->calculateNextRun('monthly', ['day_of_month' => 20, 'time' => '09:00'], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextMonthly);
        $this->assertSame('2026-10-20 09:00:00', $nextMonthly->format('Y-m-d H:i:s'));

        // 6. Cron
        $nextCron = $scheduler->calculateNextRun('cron', ['cron_expression' => '0 11 * * *'], 'Europe/Warsaw', $base);
        $this->assertNotNull($nextCron);
        $this->assertSame('2026-10-15 11:00:00', $nextCron->format('Y-m-d H:i:s'));
    }

    public function test_scheduler_previews_upcoming_runs(): void
    {
        /** @var ScenarioSchedulerInterface $scheduler */
        $scheduler = app(ScenarioSchedulerInterface::class);

        $runs = $scheduler->previewUpcomingRuns('daily', ['time' => '08:00'], 'Europe/Warsaw', 5);

        $this->assertCount(5, $runs);
        foreach ($runs as $run) {
            $this->assertStringContainsString('Europe/Warsaw', $run);
        }
    }

    public function test_dispatch_due_triggers_creates_run_and_updates_next_run(): void
    {
        // Utworzenie wyzwalacza, którego termin minął
        $trigger = ScenarioTrigger::create([
            'scenario_id' => $this->scenario->id,
            'name' => 'Wyzwalacz Minutowy',
            'type' => 'scheduled',
            'schedule_type' => 'interval',
            'schedule' => ['interval_minutes' => 30],
            'timezone' => 'Europe/Warsaw',
            'enabled' => true,
            'state' => ScenarioTrigger::STATE_ACTIVE,
            'next_run_at' => now()->subMinute(),
            'runs_count' => 0,
        ]);

        $this->artisan('scenarios:dispatch-due')
            ->expectsOutputToContain('uruchomiono 1 wyzwalaczy')
            ->assertSuccessful();

        $trigger->refresh();
        $this->assertSame(1, $trigger->runs_count);
        $this->assertTrue($trigger->next_run_at->isFuture());

        $this->assertDatabaseHas('scenario_runs', [
            'scenario_id' => $this->scenario->id,
            'trigger_id' => $trigger->id,
            'trigger_type' => 'scheduled',
        ]);
    }

    public function test_trigger_auto_pauses_after_five_consecutive_failures(): void
    {
        // Mock engine to throw exception
        $mockEngine = $this->createMock(ScenarioEngineInterface::class);
        $mockEngine->method('startRun')->willThrowException(new \RuntimeException('Krytyczny błąd środowiska'));
        $this->app->instance(ScenarioEngineInterface::class, $mockEngine);

        $trigger = ScenarioTrigger::create([
            'scenario_id' => $this->scenario->id,
            'name' => 'Wadliwy Wyzwalacz',
            'type' => 'scheduled',
            'schedule_type' => 'interval',
            'schedule' => ['interval_minutes' => 10],
            'timezone' => 'Europe/Warsaw',
            'enabled' => true,
            'state' => ScenarioTrigger::STATE_ACTIVE,
            'next_run_at' => now()->subMinute(),
            'consecutive_failures' => 4, // 4 wcześniejsze awarie
        ]);

        $this->artisan('scenarios:dispatch-due')->assertSuccessful();

        $trigger->refresh();
        $this->assertSame(5, $trigger->consecutive_failures);
        $this->assertSame(ScenarioTrigger::STATE_ERROR, $trigger->state);
        $this->assertNotNull($trigger->last_error);
    }

    public function test_user_can_manage_triggers_via_web_endpoints(): void
    {
        // 1. Index
        $response = $this->actingAs($this->admin)->get(route('scenarios.triggers.index', $this->scenario));
        $response->assertStatus(200);
        $response->assertSee('Harmonogramy scenariusza');

        // 2. Store
        $storeResponse = $this->actingAs($this->admin)->post(route('scenarios.triggers.store', $this->scenario), [
            'name' => 'Testowy Raport Dzienny',
            'schedule_type' => 'daily',
            'time' => '07:30',
            'timezone' => 'Europe/Warsaw',
        ]);
        $storeResponse->assertRedirect(route('scenarios.triggers.index', $this->scenario));

        $this->assertDatabaseHas('scenario_triggers', [
            'scenario_id' => $this->scenario->id,
            'name' => 'Testowy Raport Dzienny',
            'schedule_type' => 'daily',
            'state' => ScenarioTrigger::STATE_ACTIVE,
        ]);

        $trigger = ScenarioTrigger::where('name', 'Testowy Raport Dzienny')->firstOrFail();

        // 3. Toggle (Pause)
        $toggleResponse = $this->actingAs($this->admin)->post(route('scenarios.triggers.toggle', [$this->scenario, $trigger]));
        $toggleResponse->assertRedirect();
        $trigger->refresh();
        $this->assertFalse($trigger->enabled);
        $this->assertSame(ScenarioTrigger::STATE_PAUSED, $trigger->state);

        // 4. Run Now
        $runNowResponse = $this->actingAs($this->admin)->post(route('scenarios.triggers.run-now', [$this->scenario, $trigger]));
        $runNowResponse->assertRedirect();
        $this->assertDatabaseHas('scenario_runs', [
            'scenario_id' => $this->scenario->id,
            'trigger_id' => $trigger->id,
        ]);

        // 5. Preview endpoint
        $previewResponse = $this->actingAs($this->admin)->postJson(route('scenarios.triggers.preview'), [
            'schedule_type' => 'cron',
            'schedule' => ['cron_expression' => '0 12 * * *'],
            'timezone' => 'Europe/Warsaw',
        ]);
        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonStructure(['success', 'runs']);
        $this->assertCount(5, $previewResponse->json('runs'));

        // 6. Delete
        $deleteResponse = $this->actingAs($this->admin)->delete(route('scenarios.triggers.destroy', [$this->scenario, $trigger]));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('scenario_triggers', ['id' => $trigger->id]);
    }
}
