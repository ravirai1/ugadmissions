<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';

    public const STATUSES = [self::OPEN => 'Open', self::ANSWERED => 'Answered', self::CLOSED => 'Closed'];

    protected $fillable = ['user_id', 'channel', 'name', 'email', 'mobile', 'subject', 'status', 'assigned_to', 'unread_for_staff', 'unread_for_user', 'first_response_at', 'last_message_at', 'created_ip'];

    protected function casts(): array
    {
        return [
            'unread_for_staff' => 'boolean',
            'unread_for_user' => 'boolean',
            'first_response_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (Ticket $t) => $t->forceFill(['ticket_no' => 'HD27'.str_pad((string) $t->id, 5, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }
}
