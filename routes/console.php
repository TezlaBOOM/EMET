<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('agenthub:telemetry-rollup --period=hourly')->hourly();
Schedule::command('agenthub:telemetry-rollup --period=daily')->daily();
Schedule::command('agenthub:reconcile-instances')->everyThirtySeconds();

