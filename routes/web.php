<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Applicant;
use App\Http\Controllers\PageController;
use App\Support\Roles;
use Illuminate\Support\Facades\Route;

/*
| Public website
*/
Route::controller(PageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/about', 'about')->name('about');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/documents-required', 'documents')->name('documents');
    Route::get('/christian-students', 'christianGuidelines')->name('christian');
    Route::get('/legal/{page}', 'legal')->name('legal')
        ->whereIn('page', ['privacy-policy', 'terms-and-conditions', 'refund-policy', 'disclaimer']);
});

/*
| Applicant accounts
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [Applicant\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [Applicant\AuthController::class, 'register'])->middleware('throttle:register');

    Route::get('/login', [Applicant\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Applicant\AuthController::class, 'login'])->middleware(['ip.allowed:applicant', 'throttle:login']);

    Route::get('/forgot-password', [Applicant\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Applicant\PasswordResetController::class, 'email'])->middleware('throttle:forgot')->name('password.email');
    Route::get('/reset-password/{token}', [Applicant\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Applicant\PasswordResetController::class, 'update'])->middleware('throttle:reset')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Applicant\AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [Applicant\VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Applicant\VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:verify'])->name('verification.verify');
    Route::post('/email/verification-notification', [Applicant\VerificationController::class, 'resend'])
        ->middleware('throttle:verify-resend')->name('verification.send');

    Route::middleware('verified')->prefix('applicant')->name('applicant.')->group(function () {
        Route::get('/dashboard', [Applicant\DashboardController::class, 'index'])->name('dashboard');
    });

    Route::middleware('verified')->prefix('application')->name('application.')->controller(Applicant\ApplicationController::class)->group(function () {
        $steps = implode('|', App\Support\ApplicationSteps::slugs());
        Route::get('/', 'start')->name('start');
        Route::post('/submit', 'submit')->middleware('throttle:app-submit')->name('submit');
        Route::post('/documents/{type}', 'upload')->middleware('throttle:app-upload')->name('upload')->where('type', '[a-z0-9_]+');
        Route::get('/documents/file/{document}', 'document')->name('document');
        Route::get('/{step}', 'show')->name('step')->where('step', $steps);
        Route::post('/{step}', 'save')->middleware('throttle:app-save')->name('save')->where('step', $steps);
    });
});

/*
| Staff panel (separate guard, non-obvious URL)
*/
Route::prefix(config('admissions.admin_path'))->name('admin.')->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware(['ip.allowed:admin', 'throttle:admin-login']);

    Route::middleware(['auth:admin', 'ip.allowed:admin'])->group(function () {
        Route::get('/otp', [Admin\AuthController::class, 'showOtp'])->name('otp.show');
        Route::post('/otp', [Admin\AuthController::class, 'verifyOtp'])->middleware('throttle:admin-otp')->name('otp.verify');
        Route::post('/otp/resend', [Admin\AuthController::class, 'resendOtp'])->middleware('throttle:admin-otp-resend')->name('otp.resend');
    });

    Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin.session')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/password', [Admin\PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [Admin\PasswordController::class, 'update'])->name('password.update');

        Route::middleware('role:'.Roles::SUPER_ADMIN)->prefix('security')->name('security.')->group(function () {
            Route::get('/login-attempts', [Admin\SecurityController::class, 'attempts'])->name('attempts');
            Route::get('/blocked-ips', [Admin\SecurityController::class, 'blocked'])->name('blocked');
            Route::post('/blocked-ips', [Admin\SecurityController::class, 'block'])->name('block');
            Route::delete('/blocked-ips/{blockedIp}', [Admin\SecurityController::class, 'unblock'])->name('unblock');
            Route::get('/audit-log', [Admin\SecurityController::class, 'audit'])->name('audit');
        });
    });
});
