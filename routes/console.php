<?php

use App\Models\ApplicantActivityLog;
use App\Models\Application;
use App\Models\SecurityEvent;
use App\Models\StaffActivityLog;
use App\Services\AllotmentService;
use App\Services\ScrutinyService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-check unconfirmed SBI ePay payments in the background (older than the 45-minute wait, up to 72 hours).
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();

// Email reminders for scrutiny queries the applicant has not answered.
Schedule::call(fn () => app(ScrutinyService::class)->remind())->dailyAt('10:00')->timezone('Asia/Kolkata')->name('queries:remind')->withoutOverlapping();

// Make sure every paid application has its scrutiny records.
Artisan::command('scrutiny:sync', function () {
    $n = 0;
    Application::whereNotNull('fee_paid_at')->each(function ($app) use (&$n) {
        app(ScrutinyService::class)->sync($app);
        $n++;
    });
    $this->info("Checked $n paid application(s).");
})->purpose('Create missing scrutiny records for paid applications');

// Seat offers not accepted by their deadline lapse, freeing the seat for the next round.
Schedule::call(fn () => app(AllotmentService::class)->lapse())->everyFifteenMinutes()->name('offers:lapse')->withoutOverlapping();

// Website files compared with the deployed version; any change or stray PHP file is emailed to the super admins.
Schedule::command('security:integrity')->everyFiveMinutes()->withoutOverlapping();

// Activity logs and attack records older than ACTIVITY_LOG_DAYS are removed.
Schedule::command('model:prune', ['--model' => [ApplicantActivityLog::class, StaffActivityLog::class, SecurityEvent::class]])->dailyAt('03:30')->timezone('Asia/Kolkata');
