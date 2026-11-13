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
});

/*
| Applicant accounts
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [Applicant\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [Applicant\AuthController::class, 'register'])->middleware('throttle:5,10');

    Route::get('/login', [Applicant\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Applicant\AuthController::class, 'login'])->middleware(['ip.allowed:applicant', 'throttle:10,1']);

    Route::get('/forgot-password', [Applicant\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Applicant\PasswordResetController::class, 'email'])->middleware('throttle:3,10')->name('password.email');
    Route::get('/reset-password/{token}', [Applicant\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Applicant\PasswordResetController::class, 'update'])->middleware('throttle:5,10')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Applicant\AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [Applicant\VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Applicant\VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Applicant\VerificationController::class, 'resend'])
        ->middleware('throttle:3,10')->name('verification.send');

    Route::middleware('verified')->prefix('applicant')->name('applicant.')->group(function () {
        Route::get('/dashboard', [Applicant\DashboardController::class, 'index'])->name('dashboard');
    });
});

/*
| Staff panel (separate guard, non-obvious URL)
*/
Route::prefix(config('admissions.admin_path'))->name('admin.')->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware(['ip.allowed:admin', 'throttle:10,1']);

    Route::middleware(['auth:admin', 'ip.allowed:admin'])->group(function () {
        Route::get('/otp', [Admin\AuthController::class, 'showOtp'])->name('otp.show');
        Route::post('/otp', [Admin\AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('otp.verify');
        Route::post('/otp/resend', [Admin\AuthController::class, 'resendOtp'])->middleware('throttle:3,5')->name('otp.resend');
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
