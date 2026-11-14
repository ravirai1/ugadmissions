<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicantActivityLog;
use App\Models\StaffActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes the applicant and staff activity logs (two separate tables). Only the request line is kept,
 * never form input, so passwords, OTPs and personal details never reach the log.
 */
class ActivityLogger
{
    /** Applicant pages (GET) worth recording; every applicant POST/PUT/DELETE is recorded anyway. */
    private const APPLICANT_VIEWS = ['applicant.call-letter', 'applicant.offer', 'applicant.query', 'payment.receipt', 'application.document'];

    public function applicant(string $event, ?User $user = null, ?string $loginId = null, ?Request $request = null, ?Response $response = null): void
    {
        $this->write(ApplicantActivityLog::class, ['user_id' => $user?->id], $event, $loginId, $request, $response);
    }

    public function staff(string $event, ?Admin $admin = null, ?string $loginId = null, ?Request $request = null, ?Response $response = null): void
    {
        $this->write(StaffActivityLog::class, ['admin_id' => $admin?->id], $event, $loginId, $request, $response);
    }

    /** Called by the LogActivity middleware after every web request. */
    public function request(Request $request, Response $response): void
    {
        $route = $request->route()?->getName();
        if (! $route) {
            return;
        }
        $isStaffArea = $request->is(config('admissions.admin_path').'*');
        $changes = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isStaffArea && ($admin = $request->user('admin'))) {
            // Staff: every page and action, so we know who looked at which application.
            if (! in_array($route, ['admin.login', 'admin.logout', 'admin.otp.verify'], true)) {
                $this->staff($route, $admin, null, $request, $response);
            }
        } elseif (! $isStaffArea && ($user = $request->user('web'))) {
            if (($changes || in_array($route, self::APPLICANT_VIEWS, true)) && ! in_array($route, ['login', 'logout'], true)) {
                $this->applicant($route, $user, null, $request, $response);
            }
        }
    }

    private function write(string $model, array $who, string $event, ?string $loginId, ?Request $request, ?Response $response): void
    {
        $request ??= request();
        try {
            $model::create($who + [
                'login_id' => $loginId ? Str::limit(strtolower($loginId), 150, '') : null,
                'event' => Str::limit($event, 60, ''),
                'method' => $request?->method(),
                'path' => $request ? Str::limit('/'.ltrim($request->path(), '/'), 255, '') : null,
                'status' => $response?->getStatusCode(),
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
            ]);
        } catch (Throwable $e) {
            report($e); // logging must never break the page
        }
    }
}
