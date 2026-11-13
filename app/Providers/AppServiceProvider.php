<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Each limiter has its own key, so (for example) uploading documents never uses up the submit allowance.
        $limits = [
            'register' => [5, 10],          // attempts, minutes
            'login' => [10, 1],
            'forgot' => [3, 10],
            'reset' => [5, 10],
            'verify' => [6, 1],
            'verify-resend' => [3, 10],
            'admin-login' => [10, 1],
            'admin-otp' => [10, 1],
            'admin-otp-resend' => [3, 5],
            'app-save' => [60, 1],
            'app-upload' => [30, 1],
            'app-submit' => [5, 1],
        ];

        foreach ($limits as $name => [$max, $minutes]) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinutes($minutes, $max)
                ->by($name.'|'.($request->user()?->getAuthIdentifier() ?? $request->user('admin')?->getAuthIdentifier() ?? $request->ip())));
        }
    }
}
