<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SbiEpayCrypto;
use App\Services\Payments\SbiEpayGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            $cfg = config('payment.sbi_epay');

            if ($cfg['driver'] === 'fake') {
                if ($app->environment('production')) {
                    throw new RuntimeException('The fake payment gateway cannot be used in production. Set SBI_EPAY_DRIVER=sbi.');
                }

                return new FakeGateway;
            }

            return new SbiEpayGateway($cfg, new SbiEpayCrypto((string) $cfg['key'], (string) $cfg['cipher']));
        });
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
            'pay-initiate' => [10, 10],
            'pay-check' => [6, 10],
            'pay-gateway' => [60, 1],
            'admin-pay-check' => [60, 1],
        ];

        foreach ($limits as $name => [$max, $minutes]) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinutes($minutes, $max)
                ->by($name.'|'.($request->user()?->getAuthIdentifier() ?? $request->user('admin')?->getAuthIdentifier() ?? $request->ip())));
        }
    }
}
