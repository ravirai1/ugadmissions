<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatOffer extends Model
{
    public const OFFERED = 'offered';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const LAPSED = 'lapsed';
    public const UPGRADED = 'upgraded';

    public const STATUSES = [self::OFFERED => 'Offered', self::ACCEPTED => 'Accepted', self::DECLINED => 'Declined', self::LAPSED => 'Lapsed', self::UPGRADED => 'Moved to higher preference'];

    /** Offers that hold a seat. */
    public const ACTIVE = [self::OFFERED, self::ACCEPTED];

    protected $fillable = ['round', 'application_id', 'application_course_id', 'course_id', 'seat_category', 'merit_rank', 'cuet_part', 'interview_part', 'total', 'status', 'deadline_at', 'created_by'];

    protected function casts(): array
    {
        return ['deadline_at' => 'datetime', 'responded_at' => 'datetime', 'emailed_at' => 'datetime', 'cuet_part' => 'decimal:2', 'interview_part' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::created(fn (SeatOffer $o) => $o->forceFill(['offer_no' => 'SSC27OF'.str_pad((string) $o->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
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

    public function upgradedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upgraded_from_id');
    }

    public function canAccept(): bool
    {
        return $this->status === self::OFFERED && $this->deadline_at->isFuture();
    }
}
