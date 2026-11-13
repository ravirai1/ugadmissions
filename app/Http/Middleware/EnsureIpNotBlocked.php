<?php

namespace App\Http\Middleware;

use App\Services\LoginGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIpNotBlocked
{
    public function __construct(private LoginGuard $guard) {}

    public function handle(Request $request, Closure $next, string $guard = 'applicant'): Response
    {
        if ($this->guard->isBlocked($request->ip(), $guard)) {
            $until = $this->guard->blockedUntil($request->ip(), $guard);
            $msg = 'Too many failed login attempts from your network. '
                .($until ? 'Please try again after '.$until->timezone('Asia/Kolkata')->format('h:i A').'.' : 'Please contact the admissions office.');

            abort(429, $msg);
        }

        return $next($request);
    }
}
