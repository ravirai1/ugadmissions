<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\Application;
use App\Models\LoginAttempt;
use App\Models\Payment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Application, payment and course/category statistics are added in later steps.
        return view('admin.dashboard', [
            'registrations' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'todayRegistrations' => User::whereDate('created_at', today())->count(),
            'failedLoginsToday' => LoginAttempt::where('successful', false)->whereDate('created_at', today())->count(),
            'blockedIps' => BlockedIp::active()->count(),
            'submitted' => Application::where('status', Application::SUBMITTED)->count(),
            'feePaid' => Application::whereNotNull('fee_paid_at')->count(),
            'collected' => (float) Payment::where('status', Payment::SUCCESS)->sum('amount'),
            'collectedToday' => (float) Payment::where('status', Payment::SUCCESS)->whereDate('paid_at', today())->sum('amount'),
            'unconfirmed' => Payment::whereIn('status', [Payment::INITIATED, Payment::PENDING])
                ->where('created_at', '<=', now()->subMinutes(config('payment.status_check_after_minutes')))->count(),
            'duplicates' => Payment::where('is_duplicate', true)->count(),
        ]);
    }
}
