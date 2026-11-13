<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Notifications\AdminOtp;
use App\Rules\ValidCaptcha;
use App\Services\LoginGuard;
use App\Services\MathCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private LoginGuard $loginGuard, private MathCaptcha $captcha) {}

    public function showLogin(Request $request)
    {
        if (Auth::guard('admin')->check() && $request->session()->get('admin_otp_verified')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login', ['captcha' => $this->captcha->generate('admin_login')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', new ValidCaptcha('admin_login')],
        ]);
        $email = strtolower(trim($data['email']));

        $admin = Admin::where('email', $email)->first();

        // Same error for unknown email, wrong password or inactive account.
        if (! $admin || ! $admin->is_active || ! Hash::check($data['password'], $admin->password)) {
            $this->loginGuard->recordFailure($request, 'admin', $email);

            return back()->withInput(['email' => $email])
                ->withErrors(['email' => 'Invalid login details.']);
        }

        $request->session()->regenerate();
        Auth::guard('admin')->login($admin);
        $request->session()->put('admin_otp_verified', false);

        if (! config('admissions.otp.enabled')) {
            return $this->completeLogin($request, $admin);
        }

        $this->sendOtp($admin);

        return redirect()->route('admin.otp.show');
    }

    public function showOtp(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.otp', ['email' => $this->maskEmail($admin->email)]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $valid = $admin->otp_hash
            && $admin->otp_expires_at?->isFuture()
            && $admin->otp_attempts < config('admissions.otp.max_attempts')
            && Hash::check($request->otp, $admin->otp_hash);

        if (! $valid) {
            $admin->increment('otp_attempts');
            $this->loginGuard->recordFailure($request, 'admin', $admin->email);

            if ($admin->otp_attempts >= config('admissions.otp.max_attempts') || ! $admin->otp_expires_at?->isFuture()) {
                $this->forceLogout($request);

                return redirect()->route('admin.login')->withErrors(['email' => 'Code expired or too many wrong codes. Please log in again.']);
            }

            return back()->withErrors(['otp' => 'Incorrect code.']);
        }

        return $this->completeLogin($request, $admin);
    }

    public function resendOtp(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return redirect()->route('admin.login');
        }
        $this->sendOtp($admin);

        return back()->with('status', 'A new code has been sent.');
    }

    public function logout(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            AuditLog::record('auth.logout');
        }
        $this->forceLogout($request);

        return redirect()->route('admin.login')->with('status', 'You have been logged out.');
    }

    private function completeLogin(Request $request, Admin $admin)
    {
        $request->session()->regenerate();
        $request->session()->put('admin_otp_verified', true);
        $request->session()->put('admin_last_activity', time());

        $admin->forceFill([
            'otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0,
            'last_login_at' => now(), 'last_login_ip' => $request->ip(),
        ])->save();

        $this->loginGuard->recordSuccess($request, 'admin', $admin->email);
        AuditLog::record('auth.login');

        return redirect()->intended(route('admin.dashboard'));
    }

    private function sendOtp(Admin $admin): void
    {
        $code = (string) random_int(100000, 999999);
        $admin->forceFill([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(config('admissions.otp.expires_minutes')),
            'otp_attempts' => 0,
        ])->save();

        $admin->notify(new AdminOtp($code));
    }

    private function forceLogout(Request $request): void
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = explode('@', $email);

        return mb_substr($user, 0, 2).str_repeat('*', max(1, mb_strlen($user) - 2)).'@'.$domain;
    }
}
