<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrutinyAppeal extends Model
{
    public const PENDING = 'pending';

    public const UPHELD = 'upheld';   // rejection stands, final

    public const ALLOWED = 'allowed'; // rejection set aside, record goes back to scrutiny

    public const STATUSES = [
        self::PENDING => 'Under review',
        self::UPHELD => 'Rejected (rejection upheld)',
        self::ALLOWED => 'Accepted (sent back for scrutiny)',
    ];

    protected $fillable = ['scrutiny_id', 'application_id', 'rejection_remarks', 'reason', 'status', 'decision_remarks', 'decided_by', 'decided_at', 'ip'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function scrutiny(): BelongsTo
    {
        return $this->belongsTo(Scrutiny::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'appeal_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
