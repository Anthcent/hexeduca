<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drains the transactional outbox (app/IntegrationEvents) so a crash
// between COMMIT and dispatch never loses an integration event. See plan
// §9/§11 — requires a real queue worker running in production for the
// ShouldQueue listeners this triggers to actually execute.
Schedule::command('integration-events:publish-outbox')->everyMinute()->withoutOverlapping();
