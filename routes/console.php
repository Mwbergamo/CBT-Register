<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the Retail catalog cache fresh. Bluehost shared hosting has no
// long-running processes, so this relies on the single cron entry described
// in docs/BLUEHOST_DEPLOYMENT.md (`php artisan schedule:run` every minute)
// to actually fire — this just declares *when*.
Schedule::command('connectwise:sync-catalog')->everyFifteenMinutes()->withoutOverlapping();

// Bluehost shared hosting can't run a persistent `queue:work` daemon, so
// instead we drain whatever is on the queue (retry pushes to ConnectWise —
// see App\Jobs\PushSolutionsListToConnectWise) once a minute and stop.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
