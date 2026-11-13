<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrutinyQuery extends Model
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';

    protected $fillable = ['application_id', 'scrutiny_id', 'raised_by', 'message', 'document_types', 'status', 'token_hash', 'expires_at', 'reply', 'replied_at', 'reply_ip', 'reminded_at', 'reminder_count', 'closed_by', 'closed_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'document_types' => 'array',
            'expires_at' => 'datetime',
            'replied_at' => 'datetime',
            'reminded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function scrutiny(): BelongsTo
    {
        return $this->belongsTo(Scrutiny::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'raised_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'query_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function linkValid(string $token): bool
    {
        return $this->isOpen() && $this->token_hash && hash_equals($this->token_hash, hash('sha256', $token))
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
