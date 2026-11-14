<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records login attempts and blocks IPs after repeated failures.
 * Guards: "admin" (staff) and "applicant".
 */
class LoginGuard
{
    public function isBlocked(string $ip, string $guard): bool
    {
        return BlockedIp::active()->where('ip', $ip)->where('guard', $guard)->exists();
    }

    public function blockedUntil(string $ip, string $guard): ?\DateTimeInterface
    {
        return BlockedIp::active()->where('ip', $ip)->where('guard', $guard)->value('blocked_until');
    }

    public function recordFailure(Request $request, string $guard, ?string $email): void
    {
        $this->log($request, $guard, $email, false);
        $this->activity($guard, 'login_failed', $email, $request);

        $cfg = config("admissions.lockout.$guard");
        $failures = LoginAttempt::where('ip', $request->ip())
            ->where('guard', $guard)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($cfg['window_minutes']))
            ->count();

        if ($failures >= $cfg['max_attempts']) {
            BlockedIp::updateOrCreate(
                ['ip' => $request->ip(), 'guard' => $guard],
                [
                    'reason' => "$failures failed logins in {$cfg['window_minutes']} minutes",
                    'blocked_until' => now()->addMinutes($cfg['block_minutes']),
                    'blocked_by' => null,
                ]
            );
            app(SecurityAlerts::class)->send("login-block:$guard:".$request->ip(), ($guard === 'admin' ? 'Staff' : 'Applicant').' login blocked after repeated failures', [
                'IP' => (string) $request->ip(),
                'Failed attempts' => "$failures in {$cfg['window_minutes']} minutes",
                'Last login id tried' => (string) $email,
                'Blocked for' => $cfg['block_minutes'].' minutes',
            ], route('admin.security.attempts', ['q' => $request->ip()]));
        }
    }

    public function recordSuccess(Request $request, string $guard, ?string $email): void
    {
        $this->log($request, $guard, $email, true);
        $this->activity($guard, 'login', $email, $request);
    }

    private function activity(string $guard, string $event, ?string $email, Request $request): void
    {
        $logger = app(ActivityLogger::class);
        if ($guard === 'admin') {
            $logger->staff($event, $email ? Admin::where('email', $email)->first() : null, $email, $request);
        } else {
            $logger->applicant($event, $email ? User::where('email', $email)->first() : null, $email, $request);
        }
    }

    private function log(Request $request, string $guard, ?string $email, bool $ok): void
    {
        LoginAttempt::create([
            'ip' => $request->ip(),
            'guard' => $guard,
            'email' => $email ? mb_substr(strtolower($email), 0, 255) : null,
            'successful' => $ok,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
