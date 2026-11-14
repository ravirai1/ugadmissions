<?php

namespace App\Services;

use App\Models\Admin;
use App\Notifications\SecurityAlert;
use App\Support\Roles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Emails super admins (or SECURITY_ALERT_EMAILS) when something unusual happens. The same alert is sent at most once per throttle window. */
class SecurityAlerts
{
    /** @param  array<string, string>  $details */
    public function send(string $key, string $title, array $details, ?string $link = null): void
    {
        if (! Cache::add('security-alert:'.sha1($key), 1, now()->addMinutes(config('security.alert_throttle_minutes', 30)))) {
            return;
        }

        try {
            $emails = config('security.alert_emails');
            $alert = new SecurityAlert($title, $details, $link);
            if ($emails) {
                Notification::route('mail', $emails)->notify($alert);
            } else {
                Notification::send(Admin::where('role', Roles::SUPER_ADMIN)->where('is_active', true)->get(), $alert);
            }
        } catch (Throwable $e) {
            report($e); // an alert must never break the request
        }
    }
}
