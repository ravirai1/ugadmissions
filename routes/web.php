<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Applicant;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\PageController;
use App\Support\ApplicationAccess;
use App\Support\ApplicationSteps;
use App\Support\MasterData;
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
    Route::get('/notices', 'notices')->name('notices');
    Route::get('/prospectus', 'prospectus')->name('prospectus');
    Route::get('/prospectus/file', 'prospectusFile')->name('prospectus.file');
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
        Route::get('/queries/{query}', [Applicant\QueryController::class, 'show'])->name('query')->whereNumber('query');
        Route::post('/queries/{query}', [Applicant\QueryController::class, 'reply'])->middleware('throttle:app-upload')->name('query.reply')->whereNumber('query');
        Route::get('/call-letter/{letter}', [Applicant\InterviewController::class, 'callLetter'])->name('call-letter')->whereNumber('letter');
        Route::get('/offer/{offer}', [Applicant\InterviewController::class, 'offer'])->name('offer')->whereNumber('offer');
        Route::post('/offer/{offer}', [Applicant\InterviewController::class, 'respond'])->middleware('throttle:app-submit')->name('offer.respond')->whereNumber('offer');
    });

    Route::middleware('verified')->prefix('application')->name('application.')->controller(Applicant\ApplicationController::class)->group(function () {
        $steps = implode('|', ApplicationSteps::slugs());
        Route::get('/', 'start')->name('start');
        Route::post('/submit', 'submit')->middleware('throttle:app-submit')->name('submit');
        Route::post('/documents/{type}', 'upload')->middleware('throttle:app-upload')->name('upload')->where('type', '[a-z0-9_]+');
        Route::get('/documents/file/{document}', 'document')->name('document');
        Route::get('/{step}', 'show')->name('step')->where('step', $steps);
        Route::post('/{step}', 'save')->middleware('throttle:app-save')->name('save')->where('step', $steps);
    });

    Route::middleware('verified')->prefix('payment')->name('payment.')->controller(Applicant\PaymentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/application', 'payApplication')->middleware('throttle:pay-initiate')->name('application');
        Route::get('/add-courses', 'addCoursesForm')->name('add-courses');
        Route::post('/add-courses', 'addCourses')->middleware('throttle:pay-initiate')->name('add-courses.pay');
        Route::post('/add-residence', 'payResidence')->middleware('throttle:pay-initiate')->name('add-residence');
        Route::post('/{payment}/check', 'check')->middleware('throttle:pay-check')->name('check')->whereNumber('payment');
        Route::get('/{payment}/receipt', 'receipt')->name('receipt')->whereNumber('payment');
    });
});

/*
| Helpdesk: query form for anyone, chat for logged-in applicants
*/
Route::get('/help', [HelpController::class, 'index'])->name('help');
Route::get('/help/query', [HelpController::class, 'queryForm'])->name('help.query');
Route::post('/help/query', [HelpController::class, 'submitQuery'])->middleware('throttle:help-query')->name('help.query.send');
Route::middleware(['auth', 'verified'])->prefix('help')->name('help.')->controller(HelpController::class)->group(function () {
    Route::get('/chat', 'chat')->name('chat');
    Route::post('/chat', 'send')->middleware('throttle:help-chat')->name('chat.send');
    Route::get('/chat/messages', 'messages')->middleware('throttle:help-poll')->name('chat.messages');
    Route::get('/attachment/{message}', 'attachment')->name('attachment')->whereNumber('message');
});

/*
| Scrutiny query reply from the emailed secure link (no login needed; the token is checked against its stored hash)
*/
Route::get('/query/{query}/{token}', [Applicant\QueryController::class, 'showByLink'])->middleware('throttle:verify')->name('query.show')->whereNumber('query');
Route::post('/query/{query}/{token}', [Applicant\QueryController::class, 'replyByLink'])->middleware('throttle:app-upload')->name('query.reply')->whereNumber('query');

/*
| SBI ePay callbacks (CSRF-exempt; trusted only after decrypting and checking with SBI's status API)
*/
Route::prefix('payment')->name('payment.')->controller(Applicant\PaymentController::class)->group(function () {
    Route::post('/sbi/response', 'response')->middleware('throttle:pay-gateway')->name('sbi.response');
    Route::post('/sbi/push', 'push')->middleware('throttle:pay-gateway')->name('sbi.push');
    Route::get('/result/{payment}', 'result')->middleware('signed')->name('result')->whereNumber('payment');

    if (config('payment.sbi_epay.driver') === 'fake' && ! app()->environment('production')) {
        Route::post('/fake', 'fakePage')->name('fake');
        Route::post('/fake/complete', 'fakeComplete')->name('fake.complete');
    }
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

        Route::get('/statistics.xlsx', [Admin\DashboardController::class, 'export'])
            ->middleware('role:'.Roles::ADMIN.','.Roles::ACCOUNTS)->name('dashboard.export');

        // Applications (each role sees only its own list; see App\Support\ApplicationAccess)
        Route::middleware('role:'.implode(',', ApplicationAccess::VIEW_ROLES))->prefix('applications')->name('applications.')
            ->controller(Admin\ApplicationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export', 'export')->name('export');
                Route::get('/{application}', 'show')->name('show')->whereNumber('application');
                Route::get('/documents/{document}', 'document')->name('document')->whereNumber('document');
            });

        // Scrutiny: academic (department faculty, by department) and religious
        Route::middleware('role:'.Roles::ADMIN.','.Roles::DEPARTMENT.','.Roles::RELIGIOUS)->prefix('scrutiny')->name('scrutiny.')
            ->controller(Admin\ScrutinyController::class)->group(function () {
                Route::get('/{type}', 'index')->name('index')->whereIn('type', ['academic', 'religious']);
                Route::get('/item/{scrutiny}', 'show')->name('show')->whereNumber('scrutiny');
                Route::post('/item/{scrutiny}/decide', 'decide')->name('decide')->whereNumber('scrutiny');
                Route::post('/item/{scrutiny}/query', 'query')->name('query')->whereNumber('scrutiny');
                Route::post('/queries/{query}/close', 'closeQuery')->name('query.close')->whereNumber('query');
                Route::post('/item/{scrutiny}/reopen', 'reopen')->middleware('role:'.Roles::ADMIN)->name('reopen')->whereNumber('scrutiny');
            });

        // Master data and fees (admin)
        Route::middleware('role:'.Roles::ADMIN)->group(function () {
            $types = implode('|', array_keys(MasterData::types()));
            Route::prefix('master/{type}')->name('master.')->where(['type' => $types])->controller(Admin\MasterDataController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::post('/bulk', 'bulk')->name('bulk');
                Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');
                Route::put('/{id}', 'update')->name('update')->whereNumber('id');
                Route::post('/{id}/toggle', 'toggle')->name('toggle')->whereNumber('id');
            });
        });
        Route::middleware('role:'.Roles::SUPER_ADMIN)->group(function () {
            Route::get('/settings/fees', [Admin\SettingsController::class, 'fees'])->name('settings.fees');
            Route::put('/settings/fees', [Admin\SettingsController::class, 'updateFees'])->name('settings.fees.update');

            Route::controller(Admin\StaffController::class)->group(function () {
                Route::get('/staff', 'index')->name('staff.index');
                Route::get('/staff/create', 'create')->name('staff.create');
                Route::post('/staff', 'store')->name('staff.store');
                Route::get('/staff/{staff}/edit', 'edit')->name('staff.edit')->whereNumber('staff');
                Route::put('/staff/{staff}', 'update')->name('staff.update')->whereNumber('staff');
                Route::post('/staff/{staff}/reset-password', 'resetPassword')->name('staff.reset')->whereNumber('staff');
                Route::post('/staff/{staff}/toggle', 'toggle')->name('staff.toggle')->whereNumber('staff');
                Route::get('/departments', 'departments')->name('departments.index');
                Route::post('/departments', 'storeDepartment')->name('departments.store');
                Route::put('/departments/{department}', 'updateDepartment')->name('departments.update')->whereNumber('department');
            });
        });

        Route::middleware('role:'.Roles::ADMIN.','.Roles::ACCOUNTS)->prefix('payments')->name('payments.')->controller(Admin\PaymentController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::post('/check-pending', 'checkPending')->middleware('throttle:admin-pay-check')->name('check-pending');
            Route::get('/{payment}', 'show')->name('show')->whereNumber('payment');
            Route::post('/{payment}/check', 'check')->middleware('throttle:admin-pay-check')->name('check')->whereNumber('payment');
        });
        Route::get('/payments/{payment}/receipt', [Admin\PaymentController::class, 'receipt'])
            ->middleware('role:'.Roles::ADMIN.','.Roles::ACCOUNTS.','.Roles::PRINT)->name('payments.receipt')->whereNumber('payment');

        Route::middleware('role:'.Roles::ADMIN.','.Roles::HELPDESK)->prefix('helpdesk')->name('helpdesk.')->controller(Admin\HelpdeskController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/report', 'report')->name('report');
            Route::get('/{ticket}', 'show')->name('show')->whereNumber('ticket');
            Route::post('/{ticket}/reply', 'reply')->name('reply')->whereNumber('ticket');
            Route::get('/{ticket}/messages', 'messages')->name('messages')->whereNumber('ticket');
            Route::post('/{ticket}/assign', 'assign')->name('assign')->whereNumber('ticket');
            Route::post('/{ticket}/status', 'status')->name('status')->whereNumber('ticket');
            Route::get('/attachment/{message}', 'attachment')->name('attachment')->whereNumber('message');
        });

        Route::middleware('role:'.Roles::ADMIN)->prefix('notices')->name('notices.')->controller(Admin\NoticeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{notice}/edit', 'edit')->name('edit')->whereNumber('notice');
            Route::put('/{notice}', 'update')->name('update')->whereNumber('notice');
            Route::delete('/{notice}', 'destroy')->name('destroy')->whereNumber('notice');
            Route::post('/{notice}/email', 'email')->name('email')->whereNumber('notice');
        });

        Route::middleware('role:'.Roles::ADMIN)->prefix('prospectus')->name('prospectus.')->controller(Admin\ProspectusController::class)->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::post('/', 'update')->name('update');
            Route::delete('/', 'destroy')->name('destroy');
        });

        Route::middleware('role:'.Roles::ADMIN.','.Roles::PRINT)->prefix('print')->name('print.')->controller(Admin\PrintController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/bulk', 'bulk')->name('bulk');
            Route::get('/log', 'log')->name('log');
            Route::get('/call-letters', 'callLetters')->name('call-letters');
            Route::get('/application/{application}', 'form')->name('form')->whereNumber('application');
        });

        // Step 5: interviews, merit and seat allotment
        Route::middleware('role:'.Roles::ADMIN)->group(function () {
            Route::prefix('interviews')->name('interviews.')->controller(Admin\InterviewController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/course/{course}', 'course')->name('course')->whereNumber('course');
                Route::post('/course/{course}/issue', 'issue')->name('issue')->whereNumber('course');
                Route::post('/course/{course}/lock', 'lock')->name('lock')->whereNumber('course');
                Route::delete('/letters/{letter}', 'cancel')->name('cancel')->whereNumber('letter');
            });
            Route::get('/seats', [Admin\SeatController::class, 'index'])->name('seats.index');
            Route::put('/seats', [Admin\SeatController::class, 'update'])->name('seats.update');
            Route::prefix('merit')->name('merit.')->controller(Admin\MeritController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{course}', 'show')->name('show')->whereNumber('course');
                Route::get('/{course}/export', 'export')->name('export')->whereNumber('course');
            });
            Route::prefix('allotment')->name('allotment.')->controller(Admin\AllotmentController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/publish', 'publish')->name('publish');
                Route::post('/lapse', 'lapse')->name('lapse');
                Route::get('/selected', 'selections')->name('selections');
                Route::get('/selected/export', 'export')->name('export');
            });
        });
        Route::middleware('role:'.Roles::ADMIN.','.Roles::ATTENDANCE)->prefix('attendance')->name('attendance.')->controller(Admin\AttendanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/scan/{token}', 'scan')->name('scan')->where('token', '[a-f0-9]{40}');
            Route::post('/{letter}', 'mark')->name('mark')->whereNumber('letter');
        });
        Route::middleware('role:'.Roles::ADMIN.','.Roles::INTERVIEW_PANEL)->prefix('panel')->name('panel.')->controller(Admin\PanelController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{course}', 'save')->name('save')->whereNumber('course');
        });

        Route::middleware('role:'.Roles::SUPER_ADMIN)->prefix('security')->name('security.')->group(function () {
            Route::get('/login-attempts', [Admin\SecurityController::class, 'attempts'])->name('attempts');
            Route::get('/blocked-ips', [Admin\SecurityController::class, 'blocked'])->name('blocked');
            Route::post('/blocked-ips', [Admin\SecurityController::class, 'block'])->name('block');
            Route::delete('/blocked-ips/{blockedIp}', [Admin\SecurityController::class, 'unblock'])->name('unblock');
            Route::get('/audit-log', [Admin\SecurityController::class, 'audit'])->name('audit');
        });
    });
});
