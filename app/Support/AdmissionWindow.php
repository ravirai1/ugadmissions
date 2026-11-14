<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * When applicants can apply.
 * - Main round: open until the admin closes it or the closing date passes.
 * - Reopened round (only after the main round has closed): the admin picks vacant courses; anyone can apply
 *   for those courses (new applicants, or earlier applicants adding a course they had not applied for).
 *   Nothing else can be chosen or paid for.
 */
final class AdmissionWindow
{
    public const KEY = 'admission_window';

    /** @return array{main_open: bool, main_closes_at: ?string, reopen: bool, reopen_until: ?string, course_ids: int[]} */
    public static function settings(): array
    {
        return array_replace(
            ['main_open' => true, 'main_closes_at' => null, 'reopen' => false, 'reopen_until' => null, 'course_ids' => []],
            (array) Setting::get(self::KEY, [])
        );
    }

    public static function mainOpen(): bool
    {
        $s = self::settings();

        return $s['main_open'] && (! $s['main_closes_at'] || now()->lt(Carbon::parse($s['main_closes_at'])));
    }

    public static function reopenActive(): bool
    {
        $s = self::settings();

        return ! self::mainOpen() && $s['reopen'] && $s['course_ids']
            && (! $s['reopen_until'] || now()->lt(Carbon::parse($s['reopen_until'])));
    }

    public static function isOpen(): bool
    {
        return self::mainOpen() || self::reopenActive();
    }

    /** Course ids that may be chosen and paid for now; null means every active course. */
    public static function allowedCourseIds(): ?array
    {
        if (self::mainOpen()) {
            return null;
        }

        return self::reopenActive() ? array_map('intval', self::settings()['course_ids']) : [];
    }

    public static function allows(int $courseId): bool
    {
        $ids = self::allowedCourseIds();

        return $ids === null || in_array($courseId, $ids, true);
    }

    /** Short line for banners. */
    public static function status(): string
    {
        $s = self::settings();
        if (self::mainOpen()) {
            return $s['main_closes_at'] ? 'Applications are open until '.Carbon::parse($s['main_closes_at'])->timezone('Asia/Kolkata')->format('d M Y, h:i A').'.' : 'Applications are open.';
        }
        if (self::reopenActive()) {
            return 'Applications have reopened for vacant seats in some courses only'.($s['reopen_until'] ? ', until '.Carbon::parse($s['reopen_until'])->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '').'.';
        }

        return 'Applications are closed.';
    }
}
