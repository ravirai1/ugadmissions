<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-check unconfirmed SBI ePay payments in the background (older than the 45-minute wait, up to 72 hours).
Illuminate\Support\Facades\Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();
