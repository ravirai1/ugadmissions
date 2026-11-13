<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which applications a staff member may see.
 * - Department faculty and interview panels: paid applications with a paid course of their department.
 * - Religious scrutiny: paid applications in a Christian category.
 * - Central roles (admin, accounts, helpdesk, print): every submitted application.
 */
final class ApplicationAccess
{
    public const CENTRAL = [Roles::ADMIN, Roles::ACCOUNTS, Roles::HELPDESK, Roles::PRINT];

    public const VIEW_ROLES = [Roles::ADMIN, Roles::ACCOUNTS, Roles::HELPDESK, Roles::PRINT, Roles::DEPARTMENT, Roles::RELIGIOUS, Roles::INTERVIEW_PANEL];

    public static function scope(Admin $admin, Builder $q): Builder
    {
        $q->where('applications.status', Application::SUBMITTED);

        if ($admin->hasRole(...self::CENTRAL)) {
            return $q;
        }

        return match ($admin->role) {
            Roles::DEPARTMENT, Roles::INTERVIEW_PANEL => $q->whereNotNull('applications.fee_paid_at')
                ->whereHas('courses', fn ($c) => $c->where('payment_status', 'paid')
                    ->whereHas('course', fn ($co) => $co->where('department_id', $admin->department_id ?? 0))),
            Roles::RELIGIOUS => $q->whereNotNull('applications.fee_paid_at')->where('category_code', Category::CHRISTIAN),
            default => $q->whereRaw('1 = 0'),
        };
    }

    public static function canView(Admin $admin, Application $app): bool
    {
        return self::scope($admin, Application::query())->whereKey($app->id)->exists();
    }
}
