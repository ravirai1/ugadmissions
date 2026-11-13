<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallLetter extends Model
{
    public const PRESENT = 'present';
    public const ABSENT = 'absent';

    protected $fillable = ['application_id', 'application_course_id', 'course_id', 'interview_date', 'reporting_time', 'venue', 'qr_token', 'issued_by'];

    protected function casts(): array
    {
        return ['interview_date' => 'date', 'emailed_at' => 'datetime', 'attendance_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (CallLetter $l) => $l->qr_token ??= bin2hex(random_bytes(20)));
        static::created(fn (CallLetter $l) => $l->forceFill(['letter_no' => 'SSC27CL'.str_pad((string) $l->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(ApplicationCourse::class, 'application_course_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(InterviewMark::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'attendance_by');
    }

    /** Average of the panel members' marks (out of 15), or null if nobody has scored yet. */
    public function interviewScore(): ?float
    {
        $marks = $this->relationLoaded('marks') ? $this->marks : $this->marks()->get();

        return $marks->isEmpty() ? null : round($marks->avg(fn ($m) => (float) $m->marks), 2);
    }

    public function scanUrl(): string
    {
        return route('admin.attendance.scan', $this->qr_token);
    }
}
