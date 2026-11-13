<?php

use App\Http\Middleware\AdminSession;
use App\Http\Middleware\EnsureIpNotBlocked;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'ip.allowed' => EnsureIpNotBlocked::class,
            'admin.session' => AdminSession::class,
            'role' => RequireRole::class,
        ]);

        // Unauthenticated users go to the right login page for their area.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is(config('admissions.admin_path').'*')
            ? route('admin.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is(config('admissions.admin_path').'*')
            ? route('admin.dashboard')
            : route('applicant.dashboard'));

        // SBI ePay posts back to these from the bank; verified by decrypting the gateway response instead.
        // The local test gateway page is reached the same way (an auto-posted form from the payment page).
        $middleware->validateCsrfTokens(except: ['payment/sbi/response', 'payment/sbi/push', 'payment/fake']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
