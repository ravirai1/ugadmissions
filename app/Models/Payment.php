<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const INITIATED = 'initiated';
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    public const APPLICATION = 'application';
    public const ADD_COURSE = 'add_course';
    public const ADD_RESIDENCE = 'add_residence';

    public const PURPOSES = [
        self::APPLICATION => 'Application fee',
        self::ADD_COURSE => 'Additional course(s)',
        self::ADD_RESIDENCE => 'Residence',
    ];

    protected $fillable = [
        'application_id', 'order_no', 'purpose', 'items', 'amount', 'status', 'is_duplicate', 'gateway_ref', 'bank_ref',
        'pay_mode', 'gateway_status', 'gateway_reason', 'receipt_no', 'paid_at', 'last_checked_at', 'check_count', 'initiated_ip',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'amount' => 'decimal:2',
            'is_duplicate' => 'boolean',
            'paid_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->latest('id');
    }

    public function scopeUnconfirmed(Builder $q): Builder
    {
        return $q->whereIn('status', [self::INITIATED, self::PENDING, self::FAILED]);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isFinal(): bool
    {
        return $this->status === self::SUCCESS;
    }

    /** Applicants may ask for a status check once the configured wait has passed. */
    public function canCheckStatus(): bool
    {
        return ! $this->isSuccess()
            && $this->created_at->lte(now()->subMinutes(config('payment.status_check_after_minutes')));
    }

    public function checkAvailableAt(): \DateTimeInterface
    {
        return $this->created_at->copy()->addMinutes(config('payment.status_check_after_minutes'));
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? $this->purpose;
    }
}
