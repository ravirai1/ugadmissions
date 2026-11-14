<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records signed-in applicant and staff requests in their activity logs (see ActivityLogger). */
class LogActivity
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->logger->request($request, $response);

        return $response;
    }
}
