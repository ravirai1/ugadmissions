<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
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
        ]);
    }
}
