<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Application;
use App\Models\ApplicationCourse;
use App\Models\AuditLog;
use App\Models\CallLetter;
use App\Models\Course;
use App\Models\InterviewMark;
use App\Models\Setting;
use App\Notifications\CallLetterIssued;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interviews: call letters with a QR code, attendance at the desk, and panel marks out of 15.
 */
class InterviewService
{
    public static function maxMarks(): int
    {
        return (int) config('admissions.merit.interview_weight', 15);
    }

    /** Course choices eligible for this course's interview (paid, academic scrutiny approved, religious part settled). */
    public function eligible(Course $course): Collection
    {
        return ApplicationCourse::where('course_id', $course->id)->where('payment_status', 'paid')
            ->whereHas('application', fn ($a) => $a->where('status', Application::SUBMITTED)->whereNotNull('fee_paid_at'))
            ->with(['application.user', 'application.scrutinies'])
            ->get()
            ->filter(fn (ApplicationCourse $c) => $c->application->eligibleForInterview($c))
            ->values();
    }

    /** Issue call letters to the given choices (skips any that already have one). Returns the new letters. */
    public function issue(Course $course, Collection $choices, string $date, string $time, string $venue, Admin $by, bool $email = true): Collection
    {
        $existing = CallLetter::whereIn('application_course_id', $choices->pluck('id'))->pluck('application_course_id')->all();

        $letters = DB::transaction(fn () => $choices->reject(fn ($c) => in_array($c->id, $existing, true))->map(fn (ApplicationCourse $c) => CallLetter::create([
            'application_id' => $c->application_id, 'application_course_id' => $c->id, 'course_id' => $course->id,
            'interview_date' => $date, 'reporting_time' => $time, 'venue' => $venue, 'issued_by' => $by->id,
        ]))->values());

        AuditLog::record('interview.call_letters', $course, ['count' => $letters->count(), 'date' => $date]);

        if ($email) {
            foreach ($letters as $l) {
                $l->application->user->notify(new CallLetterIssued($l));
                $l->forceFill(['emailed_at' => now()])->saveQuietly();
            }
        }

        return $letters;
    }

    public function markAttendance(CallLetter $letter, string $status, Admin $by): void
    {
        $this->assertUnlocked($letter->course_id);
        if ($status === CallLetter::ABSENT && $letter->marks()->exists()) {
            throw ValidationException::withMessages(['attendance' => 'Marks have already been entered for this candidate, so they cannot be marked absent.']);
        }
        $letter->forceFill(['attendance' => $status, 'attendance_at' => now(), 'attendance_by' => $by->id])->save();
        AuditLog::record('interview.attendance', $letter, ['status' => $status]);
    }

    /** Save one panel member's marks for a present candidate. */
    public function saveMarks(CallLetter $letter, Admin $member, float $marks, ?string $remarks): InterviewMark
    {
        $this->assertUnlocked($letter->course_id);
        if ($letter->attendance !== CallLetter::PRESENT) {
            throw ValidationException::withMessages(['marks' => "{$letter->letter_no} is not marked present at the attendance desk."]);
        }

        return InterviewMark::updateOrCreate(['call_letter_id' => $letter->id, 'admin_id' => $member->id], ['marks' => $marks, 'remarks' => $remarks]);
    }

    /** Courses whose marks are final (no more attendance or marks changes); only these go into seat allotment. */
    public static function lockedCourses(): array
    {
        return array_map('intval', Setting::get('interview.locked_courses', []));
    }

    public static function isLocked(int $courseId): bool
    {
        return in_array($courseId, self::lockedCourses(), true);
    }

    public function setLocked(Course $course, bool $locked, Admin $by): void
    {
        $ids = array_values(array_diff(self::lockedCourses(), [$course->id]));
        if ($locked) {
            $ids[] = $course->id;
        }
        Setting::put('interview.locked_courses', $ids, $by->id);
        AuditLog::record($locked ? 'interview.lock' : 'interview.unlock', $course);
    }

    private function assertUnlocked(int $courseId): void
    {
        if (self::isLocked($courseId)) {
            throw ValidationException::withMessages(['locked' => 'Marks for this course have been finalised. Ask the admin to unlock them.']);
        }
    }

    /** QR code (SVG data URI) that opens the attendance page for this letter. */
    public static function qr(CallLetter $letter): string
    {
        return (new QRCode(new QROptions(['outputType' => QROutputInterface::MARKUP_SVG, 'outputBase64' => true, 'svgAddXmlHeader' => false])))->render($letter->scanUrl());
    }
}
