<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin && $admin->hasRole(...$roles), 403, 'You do not have access to this section.');

        return $next($request);
    }
}
