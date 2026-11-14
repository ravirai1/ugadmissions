<?php

namespace App\Support;

/**
 * Staff roles. Super admin is created only from the command line.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const DEPARTMENT = 'department';          // department faculty: academic scrutiny for own courses

    public const RELIGIOUS = 'religious_scrutiny';   // chaplaincy: religious scrutiny

    public const TFA = 'tfa_admin';                  // final scrutiny authority: only role that can reject

    public const ACCOUNTS = 'accounts';              // payments

    public const HELPDESK = 'helpdesk';              // chat admin

    public const PRINT = 'print';                    // print-only

    public const INTERVIEW_PANEL = 'interview_panel'; // view interview list, marks entry if permitted

    public const ATTENDANCE = 'attendance';          // tech team: mark present / absent

    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::DEPARTMENT => 'Department Faculty',
            self::RELIGIOUS => 'Religious Scrutiny',
            self::TFA => 'TFA Admin (final scrutiny decisions)',
            self::ACCOUNTS => 'Accounts',
            self::HELPDESK => 'Helpdesk / Chat Admin',
            self::PRINT => 'Print',
            self::INTERVIEW_PANEL => 'Department Nominee (Interview Panel)',
            self::ATTENDANCE => 'Attendance Desk (Tech Team)',
        ];
    }

    /** Roles a super admin may assign from the web panel (never super_admin). */
    public static function assignable(): array
    {
        return array_diff(array_keys(self::labels()), [self::SUPER_ADMIN]);
    }
}
