<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff area gate: logged in on the admin guard, OTP verified, account active,
 * idle timeout, and password changed after first login.
 */
class AdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $admin = $guard->user();

        if (! $admin || ! $request->session()->get('admin_otp_verified')) {
            return redirect()->route('admin.login');
        }

        $idle = config('admissions.admin_idle_minutes') * 60;
        $last = $request->session()->get('admin_last_activity', time());

        if (! $admin->is_active || (time() - $last) > $idle) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => $admin->is_active ? 'Your session expired due to inactivity.' : 'Your account has been deactivated.',
            ]);
        }

        $request->session()->put('admin_last_activity', time());

        if ($admin->must_change_password && ! $request->routeIs('admin.password.*', 'admin.logout')) {
            return redirect()->route('admin.password.edit');
        }

        return $next($request);
    }
}
