<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scrutiny extends Model
{
    public const ACADEMIC = 'academic';
    public const RELIGIOUS = 'religious';

    public const PENDING = 'pending';
    public const QUERY = 'query';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'Pending',
        self::QUERY => 'Query raised',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    protected $fillable = ['application_id', 'application_course_id', 'type', 'status', 'verified_cuet_score', 'checklist', 'remarks', 'reply_received', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'verified_cuet_score' => 'decimal:2',
            'reply_received' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationCourse(): BelongsTo
    {
        return $this->belongsTo(ApplicationCourse::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function queries(): HasMany
    {
        return $this->hasMany(ScrutinyQuery::class)->latest('id');
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::APPROVED, self::REJECTED], true);
    }

    public function statusLabel(): string
    {
        if ($this->type === self::RELIGIOUS && $this->status === self::REJECTED) {
            return 'Not accepted – treated as General';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }
}
