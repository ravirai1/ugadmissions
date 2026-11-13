<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-check unconfirmed SBI ePay payments in the background (older than the 45-minute wait, up to 72 hours).
Illuminate\Support\Facades\Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();

// Email reminders for scrutiny queries the applicant has not answered.
Illuminate\Support\Facades\Schedule::call(fn () => app(App\Services\ScrutinyService::class)->remind())->dailyAt('10:00')->timezone('Asia/Kolkata')->name('queries:remind')->withoutOverlapping();

// Make sure every paid application has its scrutiny records.
Illuminate\Support\Facades\Artisan::command('scrutiny:sync', function () {
    $n = 0;
    App\Models\Application::whereNotNull('fee_paid_at')->each(function ($app) use (&$n) {
        app(App\Services\ScrutinyService::class)->sync($app);
        $n++;
    });
    $this->info("Checked $n paid application(s).");
})->purpose('Create missing scrutiny records for paid applications');

// Seat offers not accepted by their deadline lapse, freeing the seat for the next round.
Illuminate\Support\Facades\Schedule::call(fn () => app(App\Services\AllotmentService::class)->lapse())->everyFifteenMinutes()->name('offers:lapse')->withoutOverlapping();
